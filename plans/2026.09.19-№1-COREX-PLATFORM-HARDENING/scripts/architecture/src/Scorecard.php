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

    public function __construct(
        private readonly string $repoRoot,
        private readonly string $planRoot,
    ) {}

    /**
     * @return array{
     *   rows: list<array<string, string>>,
     *   stats: array<string, array{0: int, 1: int, 2: int}>,
     *   packages_four_zeros: list<string>,
     * }
     */
    public function build(): array
    {
        $fileRows = $this->loadCsv($this->planRoot.'/artifacts/architecture/files.csv');
        $tableRows = $this->loadCsv($this->planRoot.'/artifacts/architecture/tables.csv');
        $configRows = $this->loadCsv($this->planRoot.'/artifacts/architecture/config-keys.csv');
        $packages = $this->livePackages();
        $testScriptDirs = $this->testPackagesFromComposerScript();
        $byPackageFiles = $this->groupByPackage($fileRows);

        $rows = [];
        $stats = array_fill_keys(self::CRITERIA, [0 => 0, 1 => 0, 2 => 0]);

        foreach ($packages as $composerName => $meta) {
            $scores = [];
            $scores['responsibility'] = $this->scoreResponsibility($composerName, $meta);
            $scores['standalone-install'] = $this->scoreStandaloneInstall($meta);
            $scores['public-api'] = $this->scorePublicApi($composerName, $meta, $byPackageFiles[$composerName] ?? []);
            $scores['config'] = $this->scoreConfig($composerName, $configRows, $byPackageFiles[$composerName] ?? []);
            $scores['extension'] = $this->scoreExtension($composerName, $meta, $byPackageFiles[$composerName] ?? []);
            $scores['data-ownership'] = $this->scoreDataOwnership($composerName, $tableRows);
            $scores['worker-safety'] = $this->scoreWorkerSafety($byPackageFiles[$composerName] ?? []);
            $scores['package-tests'] = $this->scorePackageTests($meta, $testScriptDirs);
            $scores['naming'] = $this->scoreNaming($byPackageFiles[$composerName] ?? []);
            $scores['structure'] = $this->scoreStructure($composerName, $meta);
            $scores['observability'] = $this->scoreObservability($byPackageFiles[$composerName] ?? []);
            $scores['bc-policy'] = $this->scoreBcPolicy($composerName, $meta);

            $row = ['package' => $composerName];
            foreach (self::CRITERIA as $criterion) {
                $val = (string) $scores[$criterion];
                $row[$criterion] = $val;
                $stats[$criterion][(int) $val]++;
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
        ];
    }

    /**
     * @param  list<array<string, string>>  $rows
     */
    public static function renderMarkdown(array $rows, array $stats, array $packagesFourZeros): string
    {
        $header = <<<'MD'
# Архитектурный scorecard пакетов (базовый)

Шкала 0/1/2 по двенадцати критериям. Это карта неоднородности, не рейтинг и не
KPI. Один сводный числовой показатель по критериям не строится: дыра в владении
данными не компенсируется чистыми именами. Финальный пересчёт — P17, после гейтов P3.

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
     */
    private function scoreResponsibility(string $composerName, array $meta): int
    {
        $readme = $this->readmeFirstSentence($meta['dir']);
        $packageType = (string) ($meta['composer']['extra']['corex']['package-type'] ?? '');

        if ($readme === '' || $this->looksLikeTechList($readme)) {
            return 0;
        }

        if ($packageType === '') {
            return 1;
        }

        return 2;
    }

    /**
     * @param  array{dir: string, composer: array<string, mixed>}  $meta
     */
    private function scoreStandaloneInstall(array $meta): int
    {
        $dir = $meta['dir'];
        $testsDir = $this->repoRoot.'/packages/'.$dir.'/tests';
        $hasTests = is_dir($testsDir);
        $hasComposerTest = isset($meta['composer']['scripts']['test']);

        if (! $hasTests) {
            return 0;
        }

        if (! $hasComposerTest) {
            return 1;
        }

        return 2;
    }

    /**
     * @param  list<array<string, string>>  $packageRows
     */
    private function scorePublicApi(string $composerName, array $meta, array $packageRows): int
    {
        $publicApi = $meta['composer']['extra']['corex']['public-api'] ?? null;

        if (! is_array($publicApi) || $publicApi === []) {
            return 0;
        }

        if ($this->importsForeignNonPublic($composerName, $packageRows)) {
            return 1;
        }

        return 2;
    }

    /**
     * @param  list<array<string, string>>  $packageRows
     */
    private function scoreConfig(string $composerName, array $configRows, array $packageRows): int
    {
        $hasOutside = false;
        foreach ($packageRows as $row) {
            if (($row['config_reads'] ?? '') === '') {
                continue;
            }
            $layer = $row['layer'] ?? '';
            $file = $row['file'] ?? '';

            if ($layer !== 'root' && ! str_contains($file, 'ServiceProvider.php')) {
                $hasOutside = true;

                break;
            }
        }

        foreach ($configRows as $cfg) {
            if (($cfg['owner'] ?? '') !== $composerName) {
                continue;
            }

            if (($cfg['validation'] ?? '') === 'use-site') {
                $hasOutside = true;

                break;
            }
        }

        if ($hasOutside) {
            return 0;
        }

        return 1;
    }

    /**
     * @param  list<array<string, string>>  $packageRows
     */
    private function scoreExtension(string $composerName, array $meta, array $packageRows): int
    {
        $dir = $meta['dir'];
        $src = $this->repoRoot.'/packages/'.$dir.'/src';
        $hasContracts = is_dir($src.'/Contracts');
        $hasExtend = is_dir($src.'/Extend');
        $morphWithoutContract = $this->providerMorphWithoutContract($dir);

        if ($morphWithoutContract && ! $hasContracts && ! $hasExtend) {
            return 0;
        }

        if (! $hasContracts && ! $hasExtend) {
            return 0;
        }

        if ($this->hasExtensionPointTest($dir)) {
            return 2;
        }

        return 1;
    }

    /**
     * @param  list<array<string, string>>  $tableRows
     */
    private function scoreDataOwnership(string $composerName, array $tableRows): int
    {
        $foreignWrite = false;
        $ownUnobservedPii = false;
        $ownTables = 0;

        foreach ($tableRows as $row) {
            $owner = $row['owning_package'] ?? '';
            $writers = $this->splitList($row['writers'] ?? '');

            if (in_array($composerName, $writers, true) && $owner !== $composerName) {
                $foreignWrite = true;
            }

            if ($owner !== $composerName) {
                continue;
            }
            $ownTables++;
            $pii = $row['pii_class'] ?? '';
            $retention = $row['retention_policy'] ?? '';

            if ($pii === 'unobserved' || $retention === 'unobserved') {
                $ownUnobservedPii = true;
            }
        }

        if ($foreignWrite) {
            return 0;
        }

        if ($ownTables === 0) {
            return 2;
        }

        if ($ownUnobservedPii) {
            return 1;
        }

        return 2;
    }

    /**
     * @param  list<array<string, string>>  $packageRows
     */
    private function scoreWorkerSafety(array $packageRows): int
    {
        $hasQueue = false;
        foreach ($packageRows as $row) {
            $fw = $row['framework_dependency'] ?? '';

            if (str_contains($fw, 'ShouldQueue') || str_contains($fw, 'ShouldQueueAfterCommit')) {
                $hasQueue = true;

                break;
            }
        }

        if (! $hasQueue) {
            return 2;
        }

        if ($this->packageHasIdempotencyArtifact($packageRows)) {
            return 2;
        }

        return 1;
    }

    /**
     * @param  array{dir: string, composer: array<string, mixed>}  $meta
     * @param  list<string>  $testScriptDirs
     */
    private function scorePackageTests(array $meta, array $testScriptDirs): int
    {
        $dir = $meta['dir'];

        if (! is_dir($this->repoRoot.'/packages/'.$dir.'/tests')) {
            return 0;
        }

        if (! in_array($dir, $testScriptDirs, true)) {
            return 1;
        }

        return 2;
    }

    /**
     * @param  list<array<string, string>>  $packageRows
     */
    private function scoreNaming(array $packageRows): int
    {
        $bad = 0;
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
            $bad++;
        }

        if ($bad >= 3) {
            return 0;
        }

        if ($bad >= 1) {
            return 1;
        }

        return 2;
    }

    /**
     * @param  array{dir: string, composer: array<string, mixed>}  $meta
     */
    private function scoreStructure(string $composerName, array $meta): int
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

        if ($profile === 'C' && $onlyLayerDirs && $topDirs !== []) {
            $score = 0;
        } elseif ($profile === 'A' && count($nonLayer) > 8) {
            $score = 0;
        } elseif ($profile === 'B' && $onlyLayerDirs && $topDirs !== []) {
            $score = 1;
        }

        if (isset(self::STRUCTURE_DEBT_SCORE[$composerName])) {
            $score = min($score, self::STRUCTURE_DEBT_SCORE[$composerName]);
        }

        return $score;
    }

    /**
     * @param  list<array<string, string>>  $packageRows
     */
    private function scoreObservability(array $packageRows): int
    {
        $hasEvents = false;
        $hasLog = false;
        $hasPackageEvents = false;
        $bareLogInContractsData = false;

        foreach ($packageRows as $row) {
            if (($row['events_emitted'] ?? '') !== '') {
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
            return 0;
        }

        if ($hasPackageEvents && ! $bareLogInContractsData) {
            return 2;
        }

        return 1;
    }

    /**
     * @param  array{dir: string, composer: array<string, mixed>}  $meta
     */
    private function scoreBcPolicy(string $composerName, array $meta): int
    {
        $dir = $meta['dir'];
        $hasContracts = is_dir($this->repoRoot.'/packages/'.$dir.'/src/Contracts');
        $publicApi = $meta['composer']['extra']['corex']['public-api'] ?? null;
        $hasPublicApi = is_array($publicApi) && $publicApi !== [];

        if (! $hasContracts && ! $hasPublicApi) {
            return 0;
        }

        return 1;
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
