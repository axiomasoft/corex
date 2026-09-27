<?php

declare(strict_types=1);

// P2.5 — пер-классовые вердикты (research/P2-design.md §7).
final class VerdictRules
{
    public const VERDICT_EXTRA_HEADERS = [
        'location', 'surface', 'verdict', 'suggested_namespace', 'suggested_name',
        'litmus_failed', 'reason',
    ];

    /** @var list<string> */
    private const FORBIDDEN_CAPABILITY_SEGMENTS = [
        'Media', 'Search', 'Task', 'Workflow', 'Wakeup', 'Panel', 'Crm', 'Notification',
    ];

    /** @var list<string> */
    private const LITMUS_FRAMEWORK_MARKERS = [
        'Illuminate\Database\Eloquent',
        'Illuminate\Http',
        'Illuminate\Foundation\Application',
    ];

    private string $litmusImporterMethod = '';

    public function __construct(
        private readonly string $repoRoot,
        private readonly string $planRoot,
    ) {}

    /**
     * @return array{rows: list<array<string, string>>, summary: array<string, mixed>}
     */
    public function build(): array
    {
        $fileRows = $this->loadCsv($this->planRoot.'/artifacts/architecture/files.csv');
        $symbolInternals = $this->loadSymbolInternals();
        $renameMap = $this->parseRenameRegistry();
        $typesPerFile = $this->countTypesPerFile($fileRows);
        $lineCounts = $this->lineCountsByFile($fileRows);
        $laravelMentions = $this->laravelExtraClassMentions();
        $livePackageSlugs = $this->livePackageSlugs($fileRows);
        $coreImporterPackages = $this->coreImporterPackages();

        $refIndex = $this->buildReferenceIndex($fileRows);

        $locationCounts = [];
        $surfaceCounts = [];
        $coreMove = [];

        $out = [];
        foreach ($fileRows as $row) {
            $fqcn = $row['namespace'].'\\'.$row['symbol'];
            $litmus = $this->evaluateLitmus(
                $row,
                $fqcn,
                $refIndex,
                $coreImporterPackages,
                $livePackageSlugs,
                $lineCounts,
            );

            $location = $this->resolveLocation(
                $row,
                $fqcn,
                $typesPerFile,
                $lineCounts,
                $refIndex,
                $laravelMentions,
                $renameMap,
                $litmus,
            );

            $surface = $this->resolveSurface($row, $symbolInternals);
            $verdict = $this->resolveVerdict($location, $surface);

            [$suggestedNs, $suggestedName] = $this->suggestedTargets(
                $row,
                $location,
                $renameMap,
                $litmus,
            );

            $notes = $row['notes'];

            if ($litmus['warning'] !== '') {
                $notes = $notes === '' ? $litmus['warning'] : $notes.'; '.$litmus['warning'];
            }

            $reason = $this->reasonSentence($location, $surface, $litmus, $row);

            $merged = $row;
            $merged['notes'] = $notes;
            $merged['suggested_namespace'] = $suggestedNs;
            $merged['suggested_name'] = $suggestedName;
            $merged['location'] = $location;
            $merged['surface'] = $surface;
            $merged['verdict'] = $verdict;
            $merged['litmus_failed'] = $litmus['failed_list'];
            $merged['reason'] = $reason;

            $out[] = $merged;

            $locationCounts[$location] = ($locationCounts[$location] ?? 0) + 1;
            $surfaceCounts[$surface] = ($surfaceCounts[$surface] ?? 0) + 1;

            if ($row['package'] === 'corex/core' && $location === 'MOVE') {
                $coreMove[] = $fqcn;
            }
        }

        sort($coreMove);

        return [
            'rows' => $out,
            'summary' => [
                'litmus_importer_method' => $this->litmusImporterMethod,
                'location' => $locationCounts,
                'surface' => $surfaceCounts,
                'core_move' => $coreMove,
            ],
        ];
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

    /**
     * @return array<string, string> key package|file|symbol|kind → phpdoc_internal
     */
    private function loadSymbolInternals(): array
    {
        $path = $this->planRoot.'/artifacts/architecture/symbols.csv';

        if (! is_file($path)) {
            return [];
        }

        $map = [];
        foreach ($this->loadCsv($path) as $row) {
            $key = implode('|', [
                $row['package'], $row['file'], $row['symbol'], $row['symbol_kind'],
            ]);
            $map[$key] = $row['phpdoc_internal'] ?? 'no';
        }

        return $map;
    }

    /**
     * @return array<string, string> symbol short name → target name
     */
    private function parseRenameRegistry(): array
    {
        $path = $this->repoRoot.'/docs/architecture/rename-registry.md';
        $map = [];

        if (! is_file($path)) {
            return $map;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES) ?: [];
        foreach ($lines as $line) {
            if (! str_starts_with($line, '| `')) {
                continue;
            }
            $parts = array_map('trim', explode('|', trim($line, '|')));

            if (count($parts) < 2) {
                continue;
            }
            $from = trim($parts[0], '` ');
            $to = trim($parts[1], '` ');

            if (str_contains($from, '::$') || str_contains($from, '/')) {
                continue;
            }
            $map[$from] = $to;
        }

        return $map;
    }

    /**
     * @param  list<array<string, string>>  $rows
     * @return array<string, int>
     */
    private function countTypesPerFile(array $rows): array
    {
        $counts = [];
        foreach ($rows as $row) {
            $counts[$row['file']] = ($counts[$row['file']] ?? 0) + 1;
        }

        return $counts;
    }

    /**
     * @param  list<array<string, string>>  $rows
     * @return array<string, int>
     */
    private function lineCountsByFile(array $rows): array
    {
        $counts = [];
        foreach ($rows as $row) {
            if (isset($counts[$row['file']])) {
                continue;
            }
            $path = $this->repoRoot.'/'.$row['file'];
            $src = is_file($path) ? file_get_contents($path) : false;
            $counts[$row['file']] = $src === false ? 0 : substr_count($src, "\n") + 1;
        }

        return $counts;
    }

    /**
     * @return array<string, true> FQCN mentioned in extra.laravel providers/aliases
     */
    private function laravelExtraClassMentions(): array
    {
        $mentions = [];
        foreach (glob($this->repoRoot.'/packages/*/composer.json') ?: [] as $composerPath) {
            $data = json_decode((string) file_get_contents($composerPath), true);

            if (! is_array($data)) {
                continue;
            }
            $extra = $data['extra']['laravel'] ?? [];
            foreach (['providers', 'aliases'] as $bucket) {
                $values = $extra[$bucket] ?? [];

                if (! is_array($values)) {
                    continue;
                }
                foreach ($values as $key => $value) {
                    $class = is_int($key) ? (string) $value : (string) $value;
                    $mentions[$class] = true;
                }
            }
        }

        return $mentions;
    }

    /**
     * @param  list<array<string, string>>  $rows
     * @return array<string, true> lowercase slug → true
     */
    private function livePackageSlugs(array $rows): array
    {
        $slugs = [];
        foreach ($rows as $row) {
            $pkg = $row['package'];
            $segment = str_contains($pkg, '/') ? explode('/', $pkg, 2)[1] : $pkg;
            $slugs[strtolower($segment)] = true;
        }

        return $slugs;
    }

    /**
     * @return list<string>
     */
    private function coreImporterPackages(): array
    {
        $path = $this->planRoot.'/artifacts/architecture/graphs/namespace-import.json';
        $data = json_decode((string) file_get_contents($path), true);

        if (! is_array($data)) {
            return [];
        }

        $from = [];
        foreach ($data['edges'] ?? [] as $edge) {
            if (($edge['to'] ?? '') === 'corex/core') {
                $from[$edge['from']] = true;
            }
        }

        $list = array_keys($from);
        sort($list);

        return $list;
    }

    /**
     * @param  list<array<string, string>>  $rows
     * @return array{
     *   other_files: array<string, int>,
     *   same_package_files: array<string, int>,
     *   importer_packages: array<string, list<string>>
     * }
     */
    private function buildReferenceIndex(array $rows): array
    {
        $files = [];
        foreach ($rows as $row) {
            $files[$row['file']] = true;
        }

        $contents = [];
        foreach (array_keys($files) as $relative) {
            $path = $this->repoRoot.'/'.$relative;
            $contents[$relative] = is_file($path) ? (string) file_get_contents($path) : '';
        }

        $otherFiles = [];
        $samePackageFiles = [];
        $importerPackages = [];

        foreach ($rows as $target) {
            $fqcn = $target['namespace'].'\\'.$target['symbol'];
            $otherFiles[$fqcn] = 0;
            $samePackageFiles[$fqcn] = 0;
            $importerPackages[$fqcn] = [];

            foreach ($rows as $candidate) {
                if ($candidate['file'] === $target['file']) {
                    continue;
                }
                $body = $contents[$candidate['file']] ?? '';

                if (! str_contains($body, $fqcn)) {
                    continue;
                }

                $otherFiles[$fqcn]++;

                if ($candidate['package'] === $target['package']) {
                    $samePackageFiles[$fqcn]++;
                }

                if ($candidate['package'] !== $target['package']) {
                    $importerPackages[$fqcn][$candidate['package']] = true;
                }
            }
            $importerPackages[$fqcn] = array_keys($importerPackages[$fqcn]);
            sort($importerPackages[$fqcn]);
        }

        $this->litmusImporterMethod =
            'per-symbol: уникальные composer-пакеты с FQCN в src, не считая corex/core';

        return [
            'other_files' => $otherFiles,
            'same_package_files' => $samePackageFiles,
            'importer_packages' => $importerPackages,
        ];
    }

    /**
     * @param  array<string, mixed>  $refIndex
     * @param  list<string>  $coreImporterPackages
     * @param  array<string, true>  $livePackageSlugs
     * @param  array<string, int>  $lineCounts
     * @return array{
     *   move: bool,
     *   failed: list<int>,
     *   failed_list: string,
     *   warning: string,
     *   cond5_unresolved: bool
     * }
     */
    private function evaluateLitmus(
        array $row,
        string $fqcn,
        array $refIndex,
        array $coreImporterPackages,
        array $livePackageSlugs,
        array $lineCounts,
    ): array {
        if ($row['package'] !== 'corex/core') {
            return [
                'move' => false,
                'failed' => [],
                'failed_list' => '',
                'warning' => '',
                'cond5_unresolved' => false,
            ];
        }

        $failed = [];
        $importers = $refIndex['importer_packages'][$fqcn] ?? [];
        $layer = $row['layer'];
        $cond1 = count($importers) >= 3;

        if (! $cond1 && in_array($layer, ['Contracts', 'Enums', 'Exceptions'], true)) {
            $cond1 = count($importers) >= 2;
        }

        if (! $cond1) {
            $failed[] = 1;
        }

        $hay = $row['namespace'].'\\'.$row['symbol'];
        $cond2 = true;
        foreach (self::FORBIDDEN_CAPABILITY_SEGMENTS as $segment) {
            if (str_contains($hay, '\\'.$segment.'\\')
                || str_contains($hay, '\\'.$segment)
                || str_starts_with($row['symbol'], $segment)
            ) {
                $cond2 = false;

                break;
            }
        }

        if (! $cond2) {
            $failed[] = 2;
        }

        $path = $this->repoRoot.'/'.$row['file'];
        $src = is_file($path) ? (string) file_get_contents($path) : '';
        $cond3 = true;
        foreach (self::LITMUS_FRAMEWORK_MARKERS as $marker) {
            if (str_contains($src, $marker)) {
                $cond3 = false;

                break;
            }
        }

        if (! $cond3) {
            $failed[] = 3;
        }

        $cond4 = ! str_contains($row['notes'], 'product-only');

        if (! $cond4) {
            $failed[] = 4;
        }

        $lines = $lineCounts[$row['file']] ?? 0;
        $kind = $row['symbol_kind'];
        $cond5Unresolved = false;
        $cond5 = $kind === 'interface'
            || $kind === 'enum'
            || $lines <= 200
            || in_array($layer, ['Contracts', 'Enums', 'Data'], true);

        if (! $cond5) {
            $cond5Unresolved = true;
            $failed[] = 5;
        }

        $zone = $row['zone_or_context'];
        $cond6 = true;

        if ($zone !== '' && $zone !== 'root' && $zone !== 'Concerns') {
            $slug = strtolower($zone);

            if (isset($livePackageSlugs[$slug]) && $slug !== 'core') {
                $cond6 = false;
            }
        }

        if (! $cond6) {
            $failed[] = 6;
        }

        $move = count($failed) >= 2;
        $warning = '';

        if (! $move && count($failed) === 1) {
            $warning = 'litmus-warning:'.$failed[0];
        }

        return [
            'move' => $move,
            'failed' => $failed,
            'failed_list' => implode(';', array_map('strval', $failed)),
            'warning' => $warning,
            'cond5_unresolved' => $cond5Unresolved,
        ];
    }

    /**
     * @param  array<string, int>  $typesPerFile
     * @param  array<string, int>  $lineCounts
     * @param  array<string, mixed>  $refIndex
     * @param  array<string, true>  $laravelMentions
     * @param  array<string, string>  $renameMap
     * @param  array<string, mixed>  $litmus
     */
    private function resolveLocation(
        array $row,
        string $fqcn,
        array $typesPerFile,
        array $lineCounts,
        array $refIndex,
        array $laravelMentions,
        array $renameMap,
        array $litmus,
    ): string {
        $otherRefs = $refIndex['other_files'][$fqcn] ?? 0;
        $inLaravel = isset($laravelMentions[$fqcn]);

        if ($otherRefs === 0 && ! $inLaravel) {
            return 'DELETE';
        }

        if (($typesPerFile[$row['file']] ?? 1) > 1) {
            return 'SPLIT';
        }

        $layer = $row['layer'];
        $samePkg = $refIndex['same_package_files'][$fqcn] ?? 0;
        $lines = $lineCounts[$row['file']] ?? 0;

        if (in_array($layer, ['Support', 'Internal'], true)
            && $samePkg === 1
            && $lines < 40
        ) {
            return 'INLINE';
        }

        if (isset($renameMap[$row['symbol']])) {
            return 'RENAME';
        }

        if ($row['package'] === 'corex/core' && $litmus['move']) {
            return 'MOVE';
        }

        return 'KEEP';
    }

    /**
     * @param  array<string, string>  $symbolInternals
     */
    private function resolveSurface(array $row, array $symbolInternals): string
    {
        if ($row['public_api'] === 'yes') {
            return 'PUBLIC';
        }

        $key = implode('|', [
            $row['package'], $row['file'], $row['symbol'], $row['symbol_kind'],
        ]);
        $docInternal = $symbolInternals[$key] ?? 'no';

        if ($row['visibility'] === 'internal-ns' || $docInternal === 'yes') {
            return 'INTERNAL';
        }

        return 'UNMARKED';
    }

    private function resolveVerdict(string $location, string $surface): string
    {
        if ($location === 'KEEP' && $surface === 'PUBLIC') {
            return 'PUBLIC';
        }

        if ($location === 'KEEP' && $surface === 'INTERNAL') {
            return 'INTERNAL';
        }

        return $location;
    }

    /**
     * @param  array<string, string>  $renameMap
     * @param  array<string, mixed>  $litmus
     * @return array{0: string, 1: string}
     */
    private function suggestedTargets(
        array $row,
        string $location,
        array $renameMap,
        array $litmus,
    ): array {
        if ($location === 'RENAME' && isset($renameMap[$row['symbol']])) {
            return ['—', $renameMap[$row['symbol']]];
        }

        if ($location === 'MOVE' && $row['package'] === 'corex/core') {
            $zone = $row['zone_or_context'];
            $ns = $zone !== '' && $zone !== 'root'
                ? 'CoreX\\'.$zone
                : 'CoreX\\Foundation';

            return [$ns, '—'];
        }

        return ['—', '—'];
    }

    /**
     * @param  array<string, mixed>  $litmus
     */
    private function reasonSentence(
        string $location,
        string $surface,
        array $litmus,
        array $row,
    ): string {
        if ($location === 'DELETE') {
            return 'Тип не импортируется из других src-файлов и не фигурирует в extra.laravel.';
        }

        if ($location === 'SPLIT') {
            return 'В одном файле объявлено более одного top-level типа.';
        }

        if ($location === 'INLINE') {
            return 'Support/Internal с одним потребителем в пакете и файл короче 40 строк.';
        }

        if ($location === 'RENAME') {
            return 'Короткое имя есть в rename-registry.md как «Сейчас».';
        }

        if ($location === 'MOVE') {
            return 'Core Litmus: провалено '.count($litmus['failed']).' из шести условий.';
        }

        if ($litmus['warning'] !== '') {
            return 'Core Litmus: одно предупреждение, остаётся в foundation.';
        }

        if ($surface === 'UNMARKED') {
            return 'Поверхность не помечена; location оставлен без переноса.';
        }

        return 'Правила §7.2–§7.3 не требуют переноса или переименования.';
    }
}
