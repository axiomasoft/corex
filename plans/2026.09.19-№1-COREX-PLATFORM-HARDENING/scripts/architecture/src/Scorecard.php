<?php

declare(strict_types=1);

// P2.6 — базовый scorecard 20×12 (research/P2-design.md §8).
final class Scorecard
{
    public const CRITERIA = [
        'responsibility',
        'standalone-install',
        'public-api',
        'config',
        'extension',
        'data-ownership',
        'worker-safety',
        'package-tests',
        'naming',
        'structure',
        'observability',
        'bc-policy',
    ];

    /** @var list<string> */
    public const CSV_HEADERS = ['package', ...self::CRITERIA];

    /** @var list<string> */
    private const NAMING_SUFFIXES = ['Manager', 'Helper', 'Util', 'Common'];

    /** @var list<string> */
    private const NAMING_ALLOWLIST = ['SearchManager'];

    /** @var list<string> */
    private const LAYER_TOP_DIRS = [
        'Contracts', 'Data', 'Enums', 'Events', 'Exceptions', 'Models', 'Concerns',
        'Internal', 'Support', 'Extend', 'Testing', 'PHPStan', 'Providers',
        'Console', 'Commands', 'Http', 'Jobs', 'root', 'other',
    ];

    /** @var array<string, int> composer name → structural debt score cap */
    private const STRUCTURE_DEBT_SCORE = [
        'corex/tenancy' => 1,
    ];

    /** @var array<string, string> P15 identity aliases still present in P2 CSVs */
    private const PACKAGE_ALIASES = [
        'corex/api' => 'corex/operations',
        'corex/auth' => 'corex/access',
    ];

    public function __construct(
        private readonly string $repoRoot,
        private readonly string $planRoot,
    ) {}

    /**
     * @return array{
     *   rows: list<array<string, string>>,
     *   stats: array<string, array{0: int, 1: int, 2: int}>,
     *   packages_four_zeros: list<string>,
     *   explanations: list<array{package: string, criterion: string, score: int, measurement: string}>,
     * }
     */
    public function build(): array
    {
        $fileRows = (new InventoryScanner($this->repoRoot))->scanSrc();
        $tableRows = $this->aliasCsvPackages($this->loadCsv($this->planRoot.'/artifacts/architecture/tables.csv'), ['owning_package', 'writers', 'readers']);
        $configRows = $this->aliasCsvPackages($this->loadCsv($this->planRoot.'/artifacts/architecture/config-keys.csv'), ['owner']);
        $packages = $this->livePackages();
        $testScriptDirs = $this->testPackagesFromComposerScript();
        $pristinePackages = $this->pristineInstalledPackages();
        $byPackageFiles = $this->groupByPackage($fileRows);
        $r4Exists = is_file($this->repoRoot.'/scripts/package-graph-gate.php');

        $rows = [];
        $explanations = [];
        $stats = array_fill_keys(self::CRITERIA, [0 => 0, 1 => 0, 2 => 0]);

        foreach ($packages as $composerName => $meta) {
            $packageRows = $byPackageFiles[$composerName] ?? [];
            $scored = [];
            $scored['responsibility'] = $this->scoreResponsibility($composerName, $meta);
            $scored['standalone-install'] = $this->scoreStandaloneInstall($meta, $pristinePackages);
            $scored['public-api'] = $this->scorePublicApi($composerName, $meta, $packageRows);
            $scored['config'] = $this->scoreConfig($composerName, $configRows, $packageRows, $meta);
            $scored['extension'] = $this->scoreExtension($composerName, $meta, $packageRows);
            $scored['data-ownership'] = $this->scoreDataOwnership($composerName, $tableRows);
            $scored['worker-safety'] = $this->scoreWorkerSafety($packageRows);
            $scored['package-tests'] = $this->scorePackageTests($meta, $testScriptDirs);
            $scored['naming'] = $this->scoreNaming($packageRows);
            $scored['structure'] = $this->scoreStructure($composerName, $meta);
            $scored['observability'] = $this->scoreObservability($packageRows);
            $scored['bc-policy'] = $this->scoreBcPolicy($composerName, $meta, $r4Exists);

            $row = ['package' => $composerName];
            foreach (self::CRITERIA as $criterion) {
                $val = $scored[$criterion][0];
                $row[$criterion] = (string) $val;
                $stats[$criterion][$val]++;

                if ($val < 2) {
                    $explanations[] = [
                        'package' => $composerName,
                        'criterion' => $criterion,
                        'score' => $val,
                        'measurement' => $scored[$criterion][1],
                    ];
                }
            }
            $rows[] = $row;
        }

        usort($rows, static fn (array $a, array $b): int => $a['package'] <=> $b['package']);

        $fourZeros = [];
        foreach ($rows as $row) {
            $zeros = 0;
            foreach (self::CRITERIA as $criterion) {
                if ($row[$criterion] === '0') {
                    $zeros++;
                }
            }

            if ($zeros >= 4) {
                $fourZeros[] = $row['package'];
            }
        }

        return [
            'rows' => $rows,
            'stats' => $stats,
            'packages_four_zeros' => $fourZeros,
            'explanations' => $explanations,
        ];
    }

    /**
     * @param  list<array<string, string>>  $rows
     * @param  list<array{package: string, criterion: string, score: int, measurement: string}>  $explanations
     */
    public static function renderMarkdown(array $rows, array $stats, array $packagesFourZeros, array $explanations = []): string
    {
        $header = <<<'MD'
# Архитектурный scorecard пакетов (финальный)

Шкала 0/1/2 по двенадцати критериям. Это карта неоднородности, не рейтинг и не
KPI. Один сводный числовой показатель по критериям не строится: дыра в владении
данными не компенсируется чистыми именами. Пересчёт после гейтов P3–P16.

## Оценки по пакетам

MD;

        $criteriaHeaders = self::CRITERIA;
        $out = $header."\n";
        $out .= '| package | '.implode(' | ', $criteriaHeaders)." |\n";
        $out .= '|:--|'.implode('|', array_fill(0, count($criteriaHeaders), ':--:'))."|\n";
        foreach ($rows as $row) {
            $cells = [$row['package']];
            foreach ($criteriaHeaders as $c) {
                $cells[] = $row[$c];
            }
            $out .= '| '.implode(' | ', $cells)." |\n";
        }

        $out .= "\n".self::renderHeterogeneity($rows, $stats, $packagesFourZeros);
        $out .= "\n".self::renderExplanations($explanations);

        return $out;
    }

    /**
     * @param  list<array<string, string>>  $rows
     * @param  array<string, array{0: int, 1: int, 2: int}>  $stats
     */
    private static function renderHeterogeneity(array $rows, array $stats, array $packagesFourZeros): string
    {
        $spread = [];
        foreach (self::CRITERIA as $criterion) {
            $vals = array_unique(array_map(static fn (array $r): string => $r[$criterion], $rows));

            if (in_array('0', $vals, true) && in_array('2', $vals, true)) {
                $spread[] = '`'.$criterion.'`';
            }
        }

        $uniform = [];
        foreach (self::CRITERIA as $criterion) {
            $vals = array_unique(array_map(static fn (array $r): string => $r[$criterion], $rows));

            if (count($vals) === 1) {
                $uniform[] = '`'.$criterion.'` ('.$vals[0].')';
            }
        }

        $pkgList = $packagesFourZeros === [] ? 'нет' : implode(', ', array_map(static fn (string $p) => '`'.$p.'`', $packagesFourZeros));

        return "## Неоднородность\n\n"
            .'Критерии, где размах по живым пакетам ≥ 2 (есть и 0, и 2): '
            .($spread === [] ? 'нет' : implode(', ', $spread)).".\n"
            ."Пакеты с ≥ 4 оценками 0: {$pkgList}.\n"
            .'Критерии, где все 20 пакетов имеют одну и ту же оценку: '
            .($uniform === [] ? 'нет' : implode(', ', $uniform))
            ." — кандидат снять с базового круга как неразличающий.\n";
    }

    /**
     * @param  list<array{package: string, criterion: string, score: int, measurement: string}>  $explanations
     */
    private static function renderExplanations(array $explanations): string
    {
        $out = "## Ячейки ниже 2\n\n";

        if ($explanations === []) {
            return $out."Нет ячеек с оценкой ниже 2.\n";
        }

        $out .= "| package | criterion | score | measurement |\n|:--|:--|:--:|:--|\n";
        foreach ($explanations as $row) {
            $measurement = str_replace('|', '\\|', $row['measurement']);
            $out .= '| `'.$row['package'].'` | `'.$row['criterion'].'` | '.$row['score'].' | '.$measurement." |\n";
        }

        return $out;
    }

    /**
     * @return array<string, array{dir: string, composer: array<string, mixed>}>
     */
    private function livePackages(): array
    {
        $out = [];
        foreach (glob($this->repoRoot.'/packages/*/composer.json') ?: [] as $path) {
            $dir = basename(dirname($path));

            if ($dir === 'import-export') {
                continue;
            }
            $composer = json_decode((string) file_get_contents($path), true);

            if (! is_array($composer) || ! isset($composer['name'])) {
                continue;
            }
            $out[(string) $composer['name']] = [
                'dir' => $dir,
                'composer' => $composer,
            ];
        }
        ksort($out);

        if (count($out) !== 20) {
            throw new RuntimeException('expected 20 live packages, got '.count($out));
        }

        return $out;
    }

    /** @return list<string> */
    private function testPackagesFromComposerScript(): array
    {
        $rootComposer = json_decode(
            (string) file_get_contents($this->repoRoot.'/composer.json'),
            true,
        );
        $script = (string) ($rootComposer['scripts']['test:packages'] ?? '');

        if (! preg_match('/for p in (.+?); do/s', $script, $m)) {
            throw new RuntimeException('cannot parse test:packages script');
        }

        return array_values(array_filter(explode(' ', trim($m[1]))));
    }

    /**
     * @param  array{dir: string, composer: array<string, mixed>}  $meta
     * @return array{0: int, 1: string}
     */
    private function scoreResponsibility(string $composerName, array $meta): array
    {
        $readme = $this->readmeFirstSentence($meta['dir']);
        $packageType = (string) ($meta['composer']['extra']['corex']['package-type'] ?? '');

        if ($readme === '' || $this->looksLikeTechList($readme)) {
            return [0, 'README first sentence empty or technology list'];
        }

        if ($packageType === '') {
            return [1, 'extra.corex.package-type empty'];
        }

        return [2, ''];
    }

    /**
     * @param  array{dir: string, composer: array<string, mixed>}  $meta
     * @param  list<string>  $pristinePackages
     * @return array{0: int, 1: string}
     */
    private function scoreStandaloneInstall(array $meta, array $pristinePackages): array
    {
        $dir = $meta['dir'];
        $name = (string) ($meta['composer']['name'] ?? '');
        $testsDir = $this->repoRoot.'/packages/'.$dir.'/tests';
        $hasTests = is_dir($testsDir);
        $hasComposerTest = isset($meta['composer']['scripts']['test']);

        if (! $hasTests) {
            return [0, 'packages/'.$dir.'/tests missing'];
        }

        if (! $hasComposerTest) {
            return [1, 'composer scripts.test missing'];
        }

        $packageType = (string) ($meta['composer']['extra']['corex']['package-type'] ?? '');

        if ($packageType === 'product') {
            return [2, ''];
        }

        if (! in_array($name, $pristinePackages, true)) {
            return [1, 'package absent from P17.6 pristine profile coverage'];
        }

        return [2, ''];
    }

    /**
     * @param  list<array<string, string>>  $packageRows
     * @return array{0: int, 1: string}
     */
    private function scorePublicApi(string $composerName, array $meta, array $packageRows): array
    {
        $publicApi = $meta['composer']['extra']['corex']['public-api'] ?? null;

        if (! is_array($publicApi) || $publicApi === []) {
            return [0, 'extra.corex.public-api missing'];
        }

        if ($this->importsForeignNonPublic($composerName, $packageRows)) {
            return [1, 'import graph references a foreign Internal namespace'];
        }

        return [2, ''];
    }

    /**
     * @param  list<array<string, string>>  $configRows
     * @param  list<array<string, string>>  $packageRows
     * @param  array{dir: string, composer: array<string, mixed>}  $meta
     * @return array{0: int, 1: string}
     */
    private function scoreConfig(string $composerName, array $configRows, array $packageRows, array $meta): array
    {
        $outsideFiles = [];
        foreach ($packageRows as $row) {
            if (($row['config_reads'] ?? '') === '') {
                continue;
            }
            $layer = $row['layer'] ?? '';
            $file = $row['file'] ?? '';

            if ($layer !== 'root' && ! str_contains($file, 'ServiceProvider.php')) {
                $outsideFiles[] = $file;
            }
        }

        $useSiteKeys = [];
        foreach ($configRows as $cfg) {
            if (($cfg['owner'] ?? '') !== $composerName) {
                continue;
            }

            if (($cfg['validation'] ?? '') === 'use-site') {
                $useSiteKeys[] = $cfg['key'] ?? '';
            }
        }

        if ($outsideFiles !== [] || $useSiteKeys !== []) {
            $bits = [];

            if ($outsideFiles !== []) {
                $bits[] = 'config_reads outside composition: '.implode(', ', array_slice($outsideFiles, 0, 5));
            }

            if ($useSiteKeys !== []) {
                $bits[] = 'use-site keys: '.implode(', ', array_slice($useSiteKeys, 0, 5));
            }

            return [0, implode('; ', $bits)];
        }

        $options = $this->typedOptionsClasses($meta['dir']);

        if ($options === []) {
            return [1, 'no typed *Options class in src'];
        }

        return [2, ''];
    }

    /**
     * @param  list<array<string, string>>  $packageRows
     * @return array{0: int, 1: string}
     */
    private function scoreExtension(string $composerName, array $meta, array $packageRows): array
    {
        $dir = $meta['dir'];
        $src = $this->repoRoot.'/packages/'.$dir.'/src';
        $hasContracts = $this->hasNamedSrcDirectory($src, 'Contracts');
        $hasExtend = $this->hasNamedSrcDirectory($src, 'Extend');
        $morphWithoutContract = $this->providerMorphWithoutContract($dir);

        if ($morphWithoutContract && ! $hasContracts && ! $hasExtend) {
            return [0, 'provider morph/app() without Contracts or Extend'];
        }

        if (! $hasContracts && ! $hasExtend) {
            return [0, 'no src/Contracts and no src/Extend'];
        }

        if ($this->hasExtensionPointTest($dir)) {
            return [2, ''];
        }

        return [1, 'Contracts/Extend present without extension-point test'];
    }

    /**
     * @param  list<array<string, string>>  $tableRows
     * @return array{0: int, 1: string}
     */
    private function scoreDataOwnership(string $composerName, array $tableRows): array
    {
        $foreignWrite = false;
        $ownUnobservedPii = false;
        $ownTables = 0;
        $unobserved = [];
        $foreign = [];

        foreach ($tableRows as $row) {
            $owner = $row['owning_package'] ?? '';
            $writers = $this->splitList($row['writers'] ?? '');

            if (in_array($composerName, $writers, true) && $owner !== $composerName) {
                $foreignWrite = true;
                $foreign[] = $row['table'] ?? '';
            }

            if ($owner !== $composerName) {
                continue;
            }
            $ownTables++;
            $pii = $row['pii_class'] ?? '';
            $retention = $row['retention_policy'] ?? '';

            if ($pii === 'unobserved' || $retention === 'unobserved') {
                $ownUnobservedPii = true;
                $unobserved[] = ($row['table'] ?? '').':pii='.$pii.',retention='.$retention;
            }
        }

        if ($foreignWrite) {
            return [0, 'foreign table writes: '.implode(', ', $foreign)];
        }

        if ($ownTables === 0) {
            return [2, ''];
        }

        if ($ownUnobservedPii) {
            return [1, 'unobserved PII/retention: '.implode('; ', array_slice($unobserved, 0, 5))];
        }

        return [2, ''];
    }

    /**
     * @param  list<array<string, string>>  $packageRows
     * @return array{0: int, 1: string}
     */
    private function scoreWorkerSafety(array $packageRows): array
    {
        $queued = [];
        foreach ($packageRows as $row) {
            $fw = $row['framework_dependency'] ?? '';

            if (str_contains($fw, 'ShouldQueue') || str_contains($fw, 'ShouldQueueAfterCommit')) {
                $queued[] = $row['symbol'] ?? $row['file'] ?? '';
            }
        }

        if ($queued === []) {
            return [2, ''];
        }

        if ($this->packageHasIdempotencyArtifact($packageRows)) {
            return [2, ''];
        }

        return [1, 'ShouldQueue without Data Receipt/idempotency type: '.implode(', ', array_slice($queued, 0, 5))];
    }

    /**
     * @param  array{dir: string, composer: array<string, mixed>}  $meta
     * @param  list<string>  $testScriptDirs
     * @return array{0: int, 1: string}
     */
    private function scorePackageTests(array $meta, array $testScriptDirs): array
    {
        $dir = $meta['dir'];

        if (! is_dir($this->repoRoot.'/packages/'.$dir.'/tests')) {
            return [0, 'packages/'.$dir.'/tests missing'];
        }

        if (! in_array($dir, $testScriptDirs, true)) {
            return [1, $dir.' absent from composer test:packages'];
        }

        return [2, ''];
    }

    /**
     * @param  list<array<string, string>>  $packageRows
     * @return array{0: int, 1: string}
     */
    private function scoreNaming(array $packageRows): array
    {
        $bad = [];
        foreach ($packageRows as $row) {
            if (($row['symbol_kind'] ?? '') !== 'class' && ($row['symbol_kind'] ?? '') !== 'interface') {
                continue;
            }
            $symbol = $row['symbol'] ?? '';

            if (! $this->symbolHasForbiddenSuffix($symbol)) {
                continue;
            }

            if (in_array($symbol, self::NAMING_ALLOWLIST, true)) {
                continue;
            }
            $bad[] = $symbol;
        }
        $count = count($bad);

        if ($count >= 3) {
            return [0, $count.' Manager/Helper/Util/Common types: '.implode(', ', array_slice($bad, 0, 8))];
        }

        if ($count >= 1) {
            return [1, $count.' Manager/Helper/Util/Common types: '.implode(', ', $bad)];
        }

        return [2, ''];
    }

    /**
     * @param  array{dir: string, composer: array<string, mixed>}  $meta
     * @return array{0: int, 1: string}
     */
    private function scoreStructure(string $composerName, array $meta): array
    {
        $dir = $meta['dir'];
        $srcDir = $this->repoRoot.'/packages/'.$dir.'/src';
        $fileCount = 0;
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($srcDir));
        foreach ($it as $file) {
            if ($file->isFile() && str_ends_with($file->getFilename(), '.php')) {
                $fileCount++;
            }
        }
        $profile = match (true) {
            $fileCount < 40 => 'A',
            $fileCount <= 100 => 'B',
            default => 'C',
        };

        $topDirs = [];
        foreach (scandir($srcDir) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            if (is_dir($srcDir.'/'.$entry)) {
                $topDirs[] = $entry;
            }
        }

        $nonLayer = array_values(array_filter(
            $topDirs,
            fn (string $d): bool => ! in_array($d, self::LAYER_TOP_DIRS, true),
        ));

        $onlyLayerDirs = array_diff($topDirs, self::LAYER_TOP_DIRS) === [];

        $score = 2;
        $reason = 'profile '.$profile.', '.$fileCount.' src files, non-layer dirs: '.implode(', ', $nonLayer);

        if ($profile === 'C' && $onlyLayerDirs && $topDirs !== []) {
            $score = 0;
            $reason = 'profile C with layer-first top-level ('.$fileCount.' files)';
        } elseif ($profile === 'A' && count($nonLayer) > 8) {
            $score = 0;
            $reason = 'profile A with '.count($nonLayer).' non-layer children';
        } elseif ($profile === 'B' && $onlyLayerDirs && $topDirs !== []) {
            $score = 1;
            $reason = 'profile B remains layer-first ('.$fileCount.' files)';
        }

        if (isset(self::STRUCTURE_DEBT_SCORE[$composerName])) {
            $capped = min($score, self::STRUCTURE_DEBT_SCORE[$composerName]);

            if ($capped < $score) {
                $reason .= '; debt cap '.self::STRUCTURE_DEBT_SCORE[$composerName];
            }
            $score = $capped;
        }

        return [$score, $score < 2 ? $reason : ''];
    }

    /**
     * @param  list<array<string, string>>  $packageRows
     * @return array{0: int, 1: string}
     */
    private function scoreObservability(array $packageRows): array
    {
        $hasEvents = false;
        $hasLog = false;
        $hasPackageEvents = false;
        $bareLogInContractsData = false;

        foreach ($packageRows as $row) {
            $layer = $row['layer'] ?? '';
            $file = $row['file'] ?? '';

            if (($row['events_emitted'] ?? '') !== '' || $layer === 'Events' || str_contains($file, '/Events/')) {
                $hasEvents = true;
                $hasPackageEvents = true;
            }

            if (($row['facades'] ?? '') !== '' && str_contains($row['facades'], 'Log')) {
                $hasLog = true;
                $layer = $row['layer'] ?? '';

                if ($layer === 'Contracts' || $layer === 'Data') {
                    $bareLogInContractsData = true;
                }
            }
        }

        if (! $hasEvents && ! $hasLog) {
            return [0, 'no package Events and no Log facade'];
        }

        if ($hasPackageEvents && ! $bareLogInContractsData) {
            return [2, ''];
        }

        if ($bareLogInContractsData) {
            return [1, 'Log facade in Contracts/Data'];
        }

        return [1, 'ad-hoc Log without package Events'];
    }

    /**
     * @param  array{dir: string, composer: array<string, mixed>}  $meta
     * @return array{0: int, 1: string}
     */
    private function scoreBcPolicy(string $composerName, array $meta, bool $r4Exists): array
    {
        $dir = $meta['dir'];
        $hasContracts = $this->hasNamedSrcDirectory($this->repoRoot.'/packages/'.$dir.'/src', 'Contracts');
        $publicApi = $meta['composer']['extra']['corex']['public-api'] ?? null;
        $hasPublicApi = is_array($publicApi) && $publicApi !== [];

        if (! $hasContracts && ! $hasPublicApi) {
            return [0, 'no src/Contracts and no extra.corex.public-api'];
        }

        if ($hasContracts && $hasPublicApi && $r4Exists) {
            return [2, ''];
        }

        $missing = [];

        if (! $hasContracts) {
            $missing[] = 'src/Contracts';
        }

        if (! $hasPublicApi) {
            $missing[] = 'public-api';
        }

        if (! $r4Exists) {
            $missing[] = 'R4 gate';
        }

        return [1, 'surface incomplete: '.implode(', ', $missing)];
    }

    private function hasNamedSrcDirectory(string $src, string $name): bool
    {
        if (! is_dir($src)) {
            return false;
        }

        if (is_dir($src.'/'.$name)) {
            return true;
        }
        $it = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($src, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST,
        );
        foreach ($it as $file) {
            if ($file->isDir() && $file->getFilename() === $name) {
                return true;
            }
        }

        return false;
    }

    /** @return list<string> */
    private function typedOptionsClasses(string $packageDir): array
    {
        $src = $this->repoRoot.'/packages/'.$packageDir.'/src';

        if (! is_dir($src)) {
            return [];
        }

        $found = [];
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($src));
        foreach ($it as $file) {
            if (! $file->isFile() || ! str_ends_with($file->getFilename(), 'Options.php')) {
                continue;
            }
            $found[] = $file->getFilename();
        }

        sort($found);

        return $found;
    }

    /** @return list<string> */
    private function pristineInstalledPackages(): array
    {
        $receipt = $this->planRoot.'/findings/P17-profile-receipt.json';

        if (! is_file($receipt)) {
            return [];
        }
        $decoded = json_decode((string) file_get_contents($receipt), true);

        if (! is_array($decoded)) {
            return [];
        }
        $names = [];
        foreach ($decoded['matrix'] ?? [] as $cell) {
            if (($cell['status'] ?? '') !== 'passed') {
                continue;
            }
            foreach ($cell['installed'] ?? [] as $name) {
                if (is_string($name) && str_starts_with($name, 'corex/')) {
                    $names[$name] = true;
                }
            }
            foreach ($cell['packages'] ?? [] as $name) {
                if (is_string($name) && str_starts_with($name, 'corex/')) {
                    $names[$name] = true;
                }
            }
        }
        $names = array_keys($names);
        sort($names);

        return $names;
    }

    /**
     * @param  list<array<string, string>>  $rows
     * @param  list<string>  $fields
     * @return list<array<string, string>>
     */
    private function aliasCsvPackages(array $rows, array $fields): array
    {
        foreach ($rows as &$row) {
            foreach ($fields as $field) {
                if (! isset($row[$field]) || $row[$field] === '') {
                    continue;
                }
                $parts = $this->splitList($row[$field]);
                $mapped = [];
                foreach ($parts === [] ? [$row[$field]] : $parts as $part) {
                    $mapped[] = self::PACKAGE_ALIASES[$part] ?? $part;
                }
                $row[$field] = implode(';', $mapped);
            }
        }
        unset($row);

        return $rows;
    }

    private function readmeFirstSentence(string $packageDir): string
    {
        $path = $this->repoRoot.'/packages/'.$packageDir.'/README.md';

        if (! is_file($path)) {
            return '';
        }
        foreach (file($path) ?: [] as $line) {
            $trim = trim($line);

            if ($trim === '' || str_starts_with($trim, '#')) {
                continue;
            }

            return $trim;
        }

        return '';
    }

    private function looksLikeTechList(string $line): bool
    {
        return (bool) preg_match('/^(composer|php\s|\*\*|-\s)/i', $line);
    }

    /**
     * @param  list<array<string, string>>  $packageRows
     */
    private function importsForeignNonPublic(string $ownPackage, array $packageRows): bool
    {
        foreach ($packageRows as $row) {
            $imports = $this->splitList($row['cross_package_imports'] ?? '');
            foreach ($imports as $fqcn) {
                if (! str_contains($fqcn, '\\')) {
                    continue;
                }

                if (! str_contains($fqcn, '\\Internal\\') && ! str_ends_with($fqcn, '\\Internal')) {
                    continue;
                }
                $owner = $this->composerNameForTypePrefix($fqcn);

                if ($owner !== null && $owner !== $ownPackage) {
                    return true;
                }
            }
        }

        return false;
    }

    private function composerNameForTypePrefix(string $fqcn): ?string
    {
        $prefix = explode('\\', $fqcn, 2)[0].'\\';
        foreach (glob($this->repoRoot.'/packages/*/composer.json') ?: [] as $path) {
            $composer = json_decode((string) file_get_contents($path), true);

            if (! is_array($composer)) {
                continue;
            }
            foreach ($composer['autoload']['psr-4'] ?? [] as $ns => $_dir) {
                if (str_starts_with($fqcn, rtrim((string) $ns, '\\').'\\')) {
                    return (string) ($composer['name'] ?? '');
                }
            }
        }

        return null;
    }

    private function providerMorphWithoutContract(string $packageDir): bool
    {
        $src = $this->repoRoot.'/packages/'.$packageDir.'/src';

        if (is_dir($src.'/Contracts') || is_dir($src.'/Extend')) {
            return false;
        }

        $providers = glob($src.'/*ServiceProvider.php') ?: [];
        $providers = [...$providers, ...glob($src.'/**/*ServiceProvider.php') ?: []];
        foreach ($providers as $path) {
            $content = (string) file_get_contents($path);

            if (str_contains($content, 'morphMap') || preg_match("/app\s*\(\s*['\"]/", $content)) {
                return true;
            }
        }

        return false;
    }

    private function hasExtensionPointTest(string $packageDir): bool
    {
        $tests = $this->repoRoot.'/packages/'.$packageDir.'/tests';

        if (! is_dir($tests)) {
            return false;
        }
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($tests));
        foreach ($it as $file) {
            if (! $file->isFile() || ! str_ends_with($file->getFilename(), '.php')) {
                continue;
            }
            $src = (string) file_get_contents($file->getPathname());

            if (str_contains($src, 'Extend\\') || str_contains($src, 'extensionPoints')) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<array<string, string>>  $packageRows
     */
    private function packageHasIdempotencyArtifact(array $packageRows): bool
    {
        foreach ($packageRows as $row) {
            $file = $row['file'] ?? '';

            if (! str_contains($file, '/Data/')) {
                continue;
            }
            $symbol = $row['symbol'] ?? '';

            if (stripos($symbol, 'idempoten') !== false || stripos($symbol, 'Receipt') !== false) {
                return true;
            }
        }

        return false;
    }

    private function symbolHasForbiddenSuffix(string $symbol): bool
    {
        foreach (self::NAMING_SUFFIXES as $suffix) {
            if (str_ends_with($symbol, $suffix)) {
                return true;
            }
        }

        return false;
    }

    /** @return list<string> */
    private function splitList(string $value): array
    {
        if ($value === '') {
            return [];
        }

        return array_values(array_filter(array_map('trim', explode(';', $value))));
    }

    /**
     * @param  list<array<string, string>>  $rows
     * @return array<string, list<array<string, string>>>
     */
    private function groupByPackage(array $rows): array
    {
        $out = [];
        foreach ($rows as $row) {
            $pkg = $row['package'] ?? '';
            $out[$pkg][] = $row;
        }

        return $out;
    }

    /**
     * @return list<array<string, string>>
     */
    private function loadCsv(string $path): array
    {
        $fh = fopen($path, 'rb');

        if ($fh === false) {
            throw new RuntimeException('cannot read '.$path);
        }
        $headers = fgetcsv($fh, 0, ',', '"', '\\');

        if ($headers === false) {
            fclose($fh);

            throw new RuntimeException('empty csv '.$path);
        }
        $rows = [];
        while (($cells = fgetcsv($fh, 0, ',', '"', '\\')) !== false) {
            if ($cells === [null] || $cells === []) {
                continue;
            }
            $row = [];
            foreach ($headers as $i => $header) {
                $row[$header] = $cells[$i] ?? '';
            }
            $rows[] = $row;
        }
        fclose($fh);

        return $rows;
    }
}
