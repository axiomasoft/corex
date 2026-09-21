<?php

declare(strict_types=1);

final class GraphBuilder
{
    /** @var array<string, array{name: string, dir: string, require: list<string>, psr4: array<string, string>}> */
    private array $packagesByDir = [];

    /** @var array<string, array{name: string, dir: string, require: list<string>, psr4: array<string, string>}> */
    private array $packagesByName = [];

    /** @var array<string, string> */
    private array $classIndex = [];

    /** @var array{allowlist: list<array<string, mixed>>, undeclared_dependency_exceptions: list<array<string, string>>, reservations: list<array<string, string>>, legacy_namespace_splits: list<array<string, mixed>>} */
    private array $gateConfig;

    public function __construct(
        private readonly string $repoRoot,
        private readonly string $gateConfigPath,
    ) {
        $decoded = json_decode((string) file_get_contents($gateConfigPath), true);

        if (! is_array($decoded)) {
            throw new RuntimeException('invalid gate config JSON');
        }
        $this->gateConfig = [
            'allowlist' => $decoded['allowlist'] ?? [],
            'undeclared_dependency_exceptions' => $decoded['undeclared_dependency_exceptions'] ?? [],
            'reservations' => $decoded['reservations'] ?? [],
            'legacy_namespace_splits' => $decoded['legacy_namespace_splits'] ?? [],
        ];
    }

    /**
     * @return array{
     *   composer: array<string, mixed>,
     *   namespaceImport: array<string, mixed>,
     *   runtime: array<string, mixed>,
     *   discrepanciesMarkdown: string,
     *   stats: array<string, int>,
     * }
     */
    public function build(): array
    {
        $this->loadPackages();
        $this->classIndex = $this->buildClassIndex();

        $composer = $this->buildComposerGraph();
        $import = $this->buildNamespaceImportGraph();
        $runtime = $this->buildRuntimeGraph();

        $discrepancies = $this->classifyDiscrepancies($composer, $import, $runtime);
        $markdown = $this->renderDiscrepancies($discrepancies, $import['gate_missed_count'], $import['gate_missed_example']);

        return [
            'composer' => $composer,
            'namespaceImport' => $import['graph'],
            'runtime' => $runtime,
            'discrepanciesMarkdown' => $markdown,
            'stats' => $discrepancies['counts'],
        ];
    }

    private function loadPackages(): void
    {
        $this->packagesByDir = [];
        $this->packagesByName = [];

        foreach (glob($this->repoRoot.'/packages/*/composer.json') ?: [] as $composerPath) {
            $dirName = basename(dirname($composerPath));
            $composer = json_decode((string) file_get_contents($composerPath), true);

            if (! is_array($composer) || ! isset($composer['name'])) {
                continue;
            }
            $psr4 = [];
            foreach ($composer['autoload']['psr-4'] ?? [] as $prefix => $relativeDir) {
                $psr4[rtrim((string) $prefix, '\\').'\\'] = (string) $relativeDir;
            }
            $meta = [
                'name' => (string) $composer['name'],
                'dir' => $dirName,
                'require' => array_keys($composer['require'] ?? []),
                'psr4' => $psr4,
            ];
            $this->packagesByDir[$dirName] = $meta;
            $this->packagesByName[$meta['name']] = $meta;
        }
        ksort($this->packagesByDir);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildComposerGraph(): array
    {
        $nodes = array_keys($this->packagesByName);
        sort($nodes);

        $edges = [];
        foreach ($this->packagesByName as $meta) {
            foreach ($meta['require'] as $dep) {
                if ($dep === 'php' || str_starts_with($dep, 'ext-')) {
                    continue;
                }

                if (! isset($this->packagesByName[$dep])) {
                    continue;
                }
                $edges[] = ['from' => $meta['name'], 'to' => $dep, 'source' => 'require'];
            }
        }

        usort($edges, static fn (array $a, array $b): int => [$a['from'], $a['to']] <=> [$b['from'], $b['to']]);

        return [
            'schema' => 'package-graph/composer/1',
            'nodes' => $nodes,
            'edges' => $edges,
        ];
    }

    /**
     * @return array{graph: array<string, mixed>, gate_missed_count: int, gate_missed_example: ?string}
     */
    private function buildNamespaceImportGraph(): array
    {
        /** @var array<string, array{via: array<string, true>, count: int, files: array<string, true>}> */
        $aggregated = [];
        /** @var array<string, array<string, true>> */
        $gateEdges = [];
        $groupUseExample = null;

        /** @var array<string, list<string>> */
        $edgeFiles = [];

        foreach ($this->packagesByDir as $meta) {
            $srcDir = $this->repoRoot.'/packages/'.$meta['dir'].'/src';

            if (! is_dir($srcDir)) {
                continue;
            }
            foreach ($this->walkPhpFiles($srcDir) as $absolute) {
                $relative = $this->relativePath($absolute);
                $content = (string) file_get_contents($absolute);
                $namespace = $this->parseNamespaceFromSource($content);
                $useTable = InventoryScanner::parseUseFromSource($content);

                foreach ($this->collectStaticReferences($content, $namespace, $useTable) as $ref) {
                    $owner = $this->resolveOwner($ref['fqcn']);

                    if ($owner === null || $owner === $meta['name']) {
                        continue;
                    }
                    $key = $meta['name'].'|'.$owner;
                    $aggregated[$key] ??= ['via' => [], 'count' => 0, 'files' => []];
                    $aggregated[$key]['via'][$ref['via']] = true;
                    $aggregated[$key]['count']++;
                    $aggregated[$key]['files'][$relative] = true;
                    $edgeFiles[$key] ??= [];
                    $edgeFiles[$key][$relative] = true;
                }

                foreach (explode("\n", $content) as $line) {
                    if ($groupUseExample === null && preg_match('/^\s*use\s+[^;]+\{/', $line) === 1) {
                        $groupUseExample = $relative.' — '.trim($line);
                    }
                    $import = self::parseUseImport($line);

                    if ($import === null) {
                        continue;
                    }
                    $owner = $this->resolveOwner($import);

                    if ($owner === null || $owner === $meta['name']) {
                        continue;
                    }
                    $gateEdges[$meta['name'].'|'.$owner] = true;
                }
            }
        }

        $nodes = array_keys($this->packagesByName);
        sort($nodes);

        $edges = [];
        foreach ($aggregated as $key => $data) {
            [$from, $to] = explode('|', $key, 2);
            $via = array_keys($data['via']);
            sort($via);
            $edges[] = [
                'from' => $from,
                'to' => $to,
                'via' => $via,
                'count' => $data['count'],
            ];
        }
        usort($edges, static fn (array $a, array $b): int => [$a['from'], $a['to']] <=> [$b['from'], $b['to']]);

        $gateMissed = [];
        foreach ($aggregated as $key => $data) {
            if (isset($gateEdges[$key])) {
                continue;
            }
            [$from, $to] = explode('|', $key, 2);
            $gateMissed[] = ['from' => $from, 'to' => $to, 'count' => $data['count']];
        }
        usort($gateMissed, static fn (array $a, array $b): int => [$a['from'], $a['to']] <=> [$b['from'], $b['to']]);

        $graph = [
            'schema' => 'package-graph/namespace-import/1',
            'nodes' => $nodes,
            'edges' => $edges,
            'gate_missed_edges' => $gateMissed,
        ];

        $edgeFileLists = [];
        foreach ($edgeFiles as $key => $files) {
            $list = array_keys($files);
            sort($list);
            $edgeFileLists[$key] = $list;
        }

        return [
            'graph' => $graph,
            'gate_missed_count' => count($gateMissed),
            'gate_missed_example' => $groupUseExample,
            'edge_files' => $edgeFileLists,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildRuntimeGraph(): array
    {
        $nodes = array_keys($this->packagesByName);
        sort($nodes);

        /** @var list<array<string, string>> */
        $edges = [];
        /** @var list<array<string, string>> */
        $unresolved = [];

        foreach ($this->packagesByDir as $meta) {
            $this->collectRuntimeFromComposerExtra($meta, $edges, $unresolved);
            $configDir = $this->repoRoot.'/packages/'.$meta['dir'].'/config';

            if (is_dir($configDir)) {
                foreach (glob($configDir.'/*.php') ?: [] as $configFile) {
                    $this->collectRuntimeFromConfig($meta, $this->relativePath($configFile), (string) file_get_contents($configFile), $edges, $unresolved);
                }
            }
            $srcDir = $this->repoRoot.'/packages/'.$meta['dir'].'/src';

            if (! is_dir($srcDir)) {
                continue;
            }
            foreach ($this->walkPhpFiles($srcDir) as $absolute) {
                $relative = $this->relativePath($absolute);

                if (! str_ends_with($relative, 'ServiceProvider.php')) {
                    continue;
                }
                $content = (string) file_get_contents($absolute);
                $namespace = $this->parseNamespaceFromSource($content);
                $useTable = InventoryScanner::parseUseFromSource($content);
                $this->collectRuntimeFromProvider($meta, $relative, $content, $namespace, $useTable, $edges, $unresolved);
            }
        }

        usort($edges, static fn (array $a, array $b): int => [$a['from'], $a['to'], $a['site'], $a['file']] <=> [$b['from'], $b['to'], $b['site'], $b['file']]);
        usort($unresolved, static fn (array $a, array $b): int => [$a['name'], $a['file']] <=> [$b['name'], $b['file']]);

        return [
            'schema' => 'package-graph/runtime/1',
            'nodes' => $nodes,
            'edges' => $edges,
            'unresolved' => $unresolved,
        ];
    }

    /**
     * @param  array{name: string, dir: string, require: list<string>, psr4: array<string, string>}  $meta
     * @param  list<array<string, string>>  $edges
     * @param  list<array<string, string>>  $unresolved
     */
    private function collectRuntimeFromComposerExtra(array $meta, array &$edges, array &$unresolved): void
    {
        $composerPath = $this->repoRoot.'/packages/'.$meta['dir'].'/composer.json';
        $composer = json_decode((string) file_get_contents($composerPath), true);

        if (! is_array($composer)) {
            return;
        }
        $extra = $composer['extra']['laravel'] ?? [];
        foreach (['providers', 'aliases'] as $bucket) {
            $values = $extra[$bucket] ?? [];

            if (! is_array($values)) {
                continue;
            }
            foreach ($values as $key => $value) {
                $class = is_int($key) ? (string) $value : (string) $value;
                $this->addRuntimeBinding($meta['name'], $class, 'composer-extra', 'convention', 'packages/'.$meta['dir'].'/composer.json', $edges, $unresolved);
            }
        }
    }

    /**
     * @param  array{name: string, dir: string, require: list<string>, psr4: array<string, string>}  $meta
     * @param  list<array<string, string>>  $edges
     * @param  list<array<string, string>>  $unresolved
     */
    private function collectRuntimeFromConfig(
        array $meta,
        string $relativeFile,
        string $content,
        array &$edges,
        array &$unresolved,
    ): void {
        if (preg_match_all("/['\"]\\\\?([A-Z][A-Za-z0-9_\\\\]+)['\"]/", $content, $matches)) {
            foreach ($matches[1] as $class) {
                if (! str_contains($class, '\\')) {
                    continue;
                }
                $this->addRuntimeBinding($meta['name'], $class, 'config', 'convention', $relativeFile, $edges, $unresolved);
            }
        }

        if (preg_match_all('/\b([A-Za-z_][A-Za-z0-9_\\\\]*)::class\b/', $content, $classMatches)) {
            foreach ($classMatches[1] as $name) {
                $fqcn = str_starts_with($name, '\\') ? ltrim($name, '\\') : $name;
                $this->addRuntimeBinding($meta['name'], $fqcn, 'config', 'static', $relativeFile, $edges, $unresolved);
            }
        }
    }

    /**
     * @param  array{name: string, dir: string, require: list<string>, psr4: array<string, string>}  $meta
     * @param  array<string, string>  $useTable
     * @param  list<array<string, string>>  $edges
     * @param  list<array<string, string>>  $unresolved
     */
    private function collectRuntimeFromProvider(
        array $meta,
        string $relativeFile,
        string $content,
        string $namespace,
        array $useTable,
        array &$edges,
        array &$unresolved,
    ): void {
        $patterns = [
            'bind' => '/\$this->app->(?:bind|singleton|scoped|bindIf|instance)\s*\(\s*([^,)]+)/',
            'listen' => '/(?:Event::listen|\$events->listen)\s*\(\s*([^,)]+)/',
            'self-resource' => '/\$this->(?:commands|loadRoutesFrom|loadMigrationsFrom)\s*\(\s*([^,)]+)/',
        ];

        foreach ($patterns as $site => $pattern) {
            if (preg_match_all($pattern, $content, $matches) === 0) {
                continue;
            }

            if (! isset($matches[1])) {
                continue;
            }
            foreach ($matches[1] as $raw) {
                $raw = trim($raw);

                if ($site === 'self-resource') {
                    $this->addRuntimeSelf($meta['name'], $site, $relativeFile, $edges);

                    continue;
                }
                $fqcn = $this->resolveClassExpression($raw, $namespace, $useTable);

                if ($fqcn === null) {
                    $unresolved[] = [
                        'name' => $raw,
                        'site' => $site,
                        'certainty' => 'unresolved',
                        'file' => $relativeFile,
                    ];

                    continue;
                }
                $this->addRuntimeBinding($meta['name'], $fqcn, $site, 'static', $relativeFile, $edges, $unresolved);
            }
        }
    }

    /**
     * @param  list<array<string, string>>  $edges
     */
    private function addRuntimeSelf(string $fromPackage, string $site, string $file, array &$edges): void
    {
        $edges[] = [
            'from' => $fromPackage,
            'to' => $fromPackage,
            'site' => $site,
            'kind' => 'self-resource',
            'certainty' => 'static',
            'file' => $file,
        ];
    }

    /**
     * @param  list<array<string, string>>  $edges
     * @param  list<array<string, string>>  $unresolved
     */
    private function addRuntimeBinding(
        string $fromPackage,
        string $classExpr,
        string $site,
        string $certainty,
        string $file,
        array &$edges,
        array &$unresolved,
    ): void {
        $fqcn = ltrim(trim($classExpr), '\\');

        if ($fqcn === '' || ! str_contains($fqcn, '\\')) {
            $unresolved[] = [
                'name' => $classExpr,
                'site' => $site,
                'certainty' => 'unresolved',
                'file' => $file,
            ];

            return;
        }

        $owner = $this->resolveOwner($fqcn);

        if ($owner === null) {
            $unresolved[] = [
                'name' => $fqcn,
                'site' => $site,
                'certainty' => $certainty === 'static' ? 'unresolved' : $certainty,
                'file' => $file,
            ];

            return;
        }

        if ($owner === $fromPackage && $site !== 'self-resource') {
            return;
        }

        $edges[] = [
            'from' => $fromPackage,
            'to' => $owner,
            'site' => $site,
            'certainty' => $certainty,
            'file' => $file,
        ];
    }

    /**
     * @param  array<string, string>  $useTable
     */
    private function resolveClassExpression(string $raw, string $namespace, array $useTable): ?string
    {
        $raw = trim($raw);

        if (preg_match('/^(.+?)::class$/', $raw, $m) === 1) {
            return $this->resolveTypeName($m[1], $namespace, $useTable);
        }

        if (str_starts_with($raw, "'") || str_starts_with($raw, '"')) {
            $inner = trim($raw, "'\"");

            return str_contains($inner, '\\') ? ltrim($inner, '\\') : null;
        }

        if (str_starts_with($raw, '\\')) {
            return ltrim($raw, '\\');
        }

        return $this->resolveTypeName($raw, $namespace, $useTable);
    }

    /**
     * @param  array<string, string>  $useTable
     */
    private function resolveTypeName(string $name, string $namespace, array $useTable): ?string
    {
        $name = trim($name);

        if ($name === '') {
            return null;
        }

        if (str_starts_with($name, '\\')) {
            return ltrim($name, '\\');
        }

        if (isset($useTable[$name])) {
            return $useTable[$name];
        }

        if (str_contains($name, '\\')) {
            return ltrim($name, '\\');
        }

        return $namespace !== '' ? $namespace.'\\'.$name : $name;
    }

    /**
     * @param  array<string, string>  $useTable
     * @return list<array{fqcn: string, via: string}>
     */
    private function collectStaticReferences(string $content, string $namespace, array $useTable): array
    {
        $refs = [];

        foreach ($useTable as $alias => $fqcn) {
            unset($alias);
            $refs[] = ['fqcn' => $fqcn, 'via' => 'use'];
        }

        if (preg_match_all('/\b([A-Za-z_][A-Za-z0-9_\\\\]*)::class\b/', $content, $matches)) {
            foreach ($matches[1] as $name) {
                $resolved = $this->resolveTypeName($name, $namespace, $useTable);

                if ($resolved !== null) {
                    $refs[] = ['fqcn' => $resolved, 'via' => 'class'];
                }
            }
        }

        if (preg_match_all('/\\\\([A-Z][A-Za-z0-9_\\\\]+)/', $content, $leading)) {
            foreach ($leading[1] as $name) {
                $refs[] = ['fqcn' => ltrim($name, '\\'), 'via' => 'fqcn'];
            }
        }

        return $refs;
    }

    /**
     * @param  array<string, mixed>  $composer
     * @param  array{graph: array<string, mixed>, gate_missed_count: int, gate_missed_example: ?string, edge_files: array<string, list<string>>}  $import
     * @param  array<string, mixed>  $runtime
     * @return array{groups: array<string, list<string>>, counts: array<string, int>}
     */
    private function classifyDiscrepancies(array $composer, array $import, array $runtime): array
    {
        $composerEdges = [];
        foreach ($composer['edges'] as $edge) {
            $composerEdges[$edge['from'].'|'.$edge['to']] = true;
        }

        $importEdges = [];
        foreach ($import['graph']['edges'] as $edge) {
            $importEdges[$edge['from'].'|'.$edge['to']] = true;
        }

        $runtimeEdges = [];
        foreach ($runtime['edges'] as $edge) {
            if (($edge['kind'] ?? '') === 'self-resource') {
                continue;
            }
            $runtimeEdges[$edge['from'].'|'.$edge['to']] = true;
        }

        $groups = [
            'G-IMPORT-UNDECLARED' => [],
            'G-IMPORT-STALE-REQUIRE' => [],
            'G-RUNTIME-HIDDEN' => [],
            'G-RUNTIME-UNDECLARED' => [],
            'G-GATE-BLIND' => [],
            'G-EXPLAINED' => [],
        ];

        foreach ($import['graph']['edges'] as $edge) {
            $key = $edge['from'].'|'.$edge['to'];
            $requires = $this->packagesByName[$edge['from']]['require'] ?? [];

            if (in_array($edge['to'], $requires, true)) {
                continue;
            }

            if ($this->isExplainedImport($edge['from'], $edge['to'], $import['edge_files'][$key] ?? [])) {
                $groups['G-EXPLAINED'][] = "{$edge['from']} → {$edge['to']} (allowlist/reservation/legacy)";

                continue;
            }
            $groups['G-IMPORT-UNDECLARED'][] = "{$edge['from']} → {$edge['to']} (count={$edge['count']})";
        }

        foreach ($composer['edges'] as $edge) {
            $key = $edge['from'].'|'.$edge['to'];

            if (isset($importEdges[$key]) || isset($runtimeEdges[$key])) {
                continue;
            }
            $groups['G-IMPORT-STALE-REQUIRE'][] = "{$edge['from']} → {$edge['to']}";
        }

        foreach ($runtime['edges'] as $edge) {
            if (($edge['kind'] ?? '') === 'self-resource') {
                continue;
            }
            $key = $edge['from'].'|'.$edge['to'];

            if (! isset($importEdges[$key])) {
                $groups['G-RUNTIME-HIDDEN'][] = "{$edge['from']} → {$edge['to']} ({$edge['site']})";
            }
            $requires = $this->packagesByName[$edge['from']]['require'] ?? [];

            if (! in_array($edge['to'], $requires, true)) {
                if ($this->isExplainedImport($edge['from'], $edge['to'], [$edge['file']])) {
                    $groups['G-EXPLAINED'][] = "{$edge['from']} → {$edge['to']} (runtime; explained)";

                    continue;
                }
                $groups['G-RUNTIME-UNDECLARED'][] = "{$edge['from']} → {$edge['to']} ({$edge['site']})";
            }
        }

        foreach ($import['graph']['gate_missed_edges'] as $edge) {
            $groups['G-GATE-BLIND'][] = "{$edge['from']} → {$edge['to']} (count={$edge['count']})";
        }

        $counts = [];
        foreach ($groups as $code => $lines) {
            $counts[$code] = count($lines);
        }
        $counts['composer_edges'] = count($composer['edges']);
        $counts['import_edges'] = count($import['graph']['edges']);
        $counts['runtime_edges'] = count(array_filter(
            $runtime['edges'],
            static fn (array $e): bool => ($e['kind'] ?? '') !== 'self-resource',
        ));

        return ['groups' => $groups, 'counts' => $counts];
    }

    /**
     * @param  list<string>  $files
     */
    private function isExplainedImport(string $from, string $to, array $files): bool
    {
        foreach ($this->gateConfig['allowlist'] as $entry) {
            if (($entry['from'] ?? '') !== $from || ($entry['to'] ?? '') !== $to) {
                continue;
            }
            $importFile = (string) ($entry['import_file'] ?? '');

            if ($importFile === '' || in_array($importFile, $files, true)) {
                return true;
            }
        }
        foreach ($this->gateConfig['undeclared_dependency_exceptions'] as $entry) {
            if (($entry['from'] ?? '') === $from && ($entry['to'] ?? '') === $to) {
                foreach ($files as $file) {
                    if (($entry['import_file'] ?? '') === $file) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    /**
     * @param  array{groups: array<string, list<string>>, counts: array<string, int>}  $discrepancies
     */
    private function renderDiscrepancies(array $discrepancies, int $gateMissedCount, ?string $gateExample): string
    {
        $codes = [
            'G-IMPORT-UNDECLARED',
            'G-IMPORT-STALE-REQUIRE',
            'G-RUNTIME-HIDDEN',
            'G-RUNTIME-UNDECLARED',
            'G-GATE-BLIND',
            'G-EXPLAINED',
        ];

        $lines = ['# Расхождения графов', ''];
        foreach ($codes as $code) {
            $lines[] = "## {$code}";
            $lines[] = '';

            if ($code === 'G-GATE-BLIND') {
                $lines[] = 'Число рёбер: '.$gateMissedCount;
                $lines[] = '';

                if ($gateMissedCount > 0 && $gateExample !== null) {
                    $lines[] = 'Пример group-use: '.$gateExample;
                } elseif ($gateMissedCount === 0) {
                    $lines[] = 'вхождений нет';
                }
                $lines[] = '';
                foreach ($discrepancies['groups'][$code] as $entry) {
                    $lines[] = '- '.$entry;
                }

                if ($discrepancies['groups'][$code] === [] && $gateMissedCount === 0) {
                    // already noted
                }
                $lines[] = '';

                continue;
            }

            $entries = $discrepancies['groups'][$code];

            if ($entries === []) {
                $lines[] = 'вхождений нет';
            } else {
                foreach ($entries as $entry) {
                    $lines[] = '- '.$entry;
                }
            }
            $lines[] = '';
        }

        return implode("\n", $lines);
    }

    /**
     * Oracle copy from scripts/package-graph-gate.php — do not include that script.
     */
    public static function parseUseImport(string $line): ?string
    {
        if (preg_match('/^\s*use\s+(?:function|const)\s+([^;{]+);/', $line, $matches) === 1) {
            return self::normalizeImport(trim($matches[1]));
        }

        if (preg_match('/^\s*use\s+([^;{]+);/', $line, $matches) !== 1) {
            return null;
        }

        $statement = trim($matches[1]);

        if (str_contains($statement, '{')) {
            return null;
        }

        return self::normalizeImport($statement);
    }

    private static function normalizeImport(string $statement): string
    {
        $parts = array_map(trim(...), explode(',', $statement));

        return $parts[0];
    }

    private function resolveOwner(string $import): ?string
    {
        if (isset($this->classIndex[$import])) {
            return $this->classIndex[$import];
        }

        $bestPrefix = '';
        $owner = null;

        foreach ($this->packagesByDir as $meta) {
            foreach ($meta['psr4'] as $prefix => $_relativeDir) {
                if (str_starts_with($import, $prefix) && strlen($prefix) > strlen($bestPrefix)) {
                    $bestPrefix = $prefix;
                    $owner = $meta['name'];
                }
            }
        }

        if ($owner === null) {
            return null;
        }

        if (! str_starts_with($owner, 'corex/') && ! str_starts_with($owner, 'flex/')) {
            return null;
        }

        return $owner;
    }

    /**
     * @return array<string, string>
     */
    private function buildClassIndex(): array
    {
        $index = [];

        foreach ($this->packagesByDir as $meta) {
            $srcDir = $this->repoRoot.'/packages/'.$meta['dir'].'/src';

            if (! is_dir($srcDir)) {
                continue;
            }
            foreach ($this->walkPhpFiles($srcDir) as $file) {
                $contents = (string) file_get_contents($file);

                if (! preg_match('/^\s*namespace\s+([^;]+);/m', $contents, $namespaceMatch)) {
                    continue;
                }
                $namespace = trim($namespaceMatch[1]).'\\';

                if (! preg_match_all('/\b(?:class|interface|trait|enum)\s+([A-Za-z_][A-Za-z0-9_]*)\b/', $contents, $typeMatches)) {
                    continue;
                }
                foreach ($typeMatches[1] as $shortName) {
                    $index[$namespace.$shortName] = $meta['name'];
                }
            }
        }

        ksort($index);

        return $index;
    }

    private function parseNamespaceFromSource(string $content): string
    {
        if (preg_match('/^\s*namespace\s+([^;{]+);/m', $content, $m) === 1) {
            return rtrim(trim($m[1]), '\\');
        }

        return '';
    }

    /**
     * @return list<string>
     */
    private function walkPhpFiles(string $dir): array
    {
        $files = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveCallbackFilterIterator(
                new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
                static fn (SplFileInfo $current): bool => ! $current->isDir()
                    || ! in_array($current->getFilename(), ['vendor', 'node_modules'], true),
            ),
        );

        foreach ($iterator as $file) {
            if ($file instanceof SplFileInfo && $file->getExtension() === 'php') {
                $files[] = $file->getPathname();
            }
        }

        sort($files);

        return $files;
    }

    private function relativePath(string $absolute): string
    {
        $prefix = rtrim($this->repoRoot, '/').'/';

        return str_starts_with($absolute, $prefix)
            ? substr($absolute, strlen($prefix))
            : $absolute;
    }
}
