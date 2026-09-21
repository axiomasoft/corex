<?php

declare(strict_types=1);

// P2.3 — Извлечение реестра таблиц из Schema::create в миграциях пакетов.
// Источник: packages/<pkg>/database/migrations (не tests/database).
// Разрешает: строковые литералы, class-константы в файле,
// $var = Config::xxx() через дефолты Config класса, прямые Config::xxx() вызовы.
// certainty=convention — когда имя не прямой строковый литерал.
final class TableExtractor
{
    /** 13 колонок A107 */
    public const TABLE_HEADERS = [
        'table', 'owning_package', 'migration', 'writers', 'readers',
        'tenant_scope', 'restore_policy', 'retention_policy', 'pii_class',
        'cross_package_access', 'indexes', 'upgrade_strategy', 'object_class',
    ];

    /** DATA-004 исключение: cross-package FK */
    private const CROSS_PACKAGE_EXCEPTIONS = ['aut_role_assignments'];

    /** PII-sensitive имена колонок */
    private const PII_DIRECT = ['email'];

    private const PII_CREDENTIALS = ['password', 'password_hash', 'secret', 'token', 'api_key'];

    private const PII_INDIRECT = ['phone', 'inn'];

    /** Дефолты Config:: методов → имена таблиц */
    private array $configDefaults = [];

    /** Пакет composer name → директория */
    private array $packageDirs = [];

    /** files.csv tables колонка → список пакетов-читателей */
    private array $tableReaders = [];

    private array $tableWriters = [];

    public function __construct(
        private readonly string $repoRoot,
        private readonly string $planRoot,
    ) {
        $this->loadPackageDirs();
        $this->loadConfigDefaults();
        $this->loadFileCsvTableRefs();
    }

    /** @return list<array<string, string>> */
    public function extract(): array
    {
        $tables = [];
        $migPaths = glob($this->repoRoot.'/packages/*/database/migrations', GLOB_ONLYDIR) ?: [];

        foreach ($migPaths as $migDir) {
            $pkgDir = dirname(dirname($migDir)); // packages/<name>
            $ownerPkg = $this->packageDirs[$pkgDir] ?? basename($pkgDir);
            $this->extractFromDir($migDir, $pkgDir, $ownerPkg, $tables);
        }

        // Дедупликация: побеждает лексикографически первая миграция
        $byTable = [];
        foreach ($tables as $row) {
            $t = $row['table'];

            if (! isset($byTable[$t])) {
                $byTable[$t] = $row;
            } else {
                // Добавить вторую миграцию в notes
                $existing = $byTable[$t]['notes'] ?? '';
                $note = 'duplicate-create:'.$row['migration'];
                $byTable[$t]['notes'] = $existing !== '' ? $existing.'; '.$note : $note;
            }
        }

        $rows = array_values($byTable);

        // Обогатить writers/readers из files.csv
        foreach ($rows as &$row) {
            $t = $row['table'];
            $writers = $this->tableWriters[$t] ?? [];

            if (! in_array($row['owning_package'], $writers, true)) {
                array_unshift($writers, $row['owning_package']);
            }
            $row['writers'] = implode(';', array_unique($writers));
            $row['readers'] = implode(';', array_unique($this->tableReaders[$t] ?? []));
        }
        unset($row);

        // Сортировка: (owning_package, table)
        usort($rows, static fn (array $a, array $b) => [$a['owning_package'], $a['table']] <=> [$b['owning_package'], $b['table']]);

        return $rows;
    }

    private function extractFromDir(
        string $migDir,
        string $pkgDir,
        string $ownerPkg,
        array &$tables,
    ): void {
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($migDir));
        $files = [];
        foreach ($it as $file) {
            if (! $file->isFile() || ! str_ends_with($file->getPathname(), '.php')) {
                continue;
            }

            // Исключить tests/database
            if (str_contains($file->getPathname(), '/tests/')) {
                continue;
            }
            $files[] = $file->getPathname();
        }
        sort($files); // лексикографический порядок

        foreach ($files as $filePath) {
            $relPath = ltrim(str_replace($this->repoRoot, '', $filePath), '/');
            $tenantScope = $this->resolveTenantScope($filePath);
            $src = file_get_contents($filePath);

            if ($src === false) {
                continue;
            }

            // Собрать class-константы в файле: const X = 'value'
            $consts = [];

            if (preg_match_all(
                '/(?:private|protected|public)?\s*const\s+(?:string\s+)?(\w+)\s*=\s*\'([^\']+)\'/',
                $src,
                $m,
            )) {
                foreach ($m[1] as $i => $name) {
                    $consts[$name] = $m[2][$i];
                }
            }

            // Собрать переменные $x = Config::xxxTable() → default
            $varMap = [];

            if (preg_match_all(
                '/\$(\w+)\s*=\s*Config::(\w+)\(\)/',
                $src,
                $m,
            )) {
                foreach ($m[1] as $i => $varName) {
                    $method = $m[2][$i];

                    if (isset($this->configDefaults[$method])) {
                        $varMap[$varName] = $this->configDefaults[$method];
                    }
                }
            }

            // Найти все Schema::create(
            if (! preg_match_all(
                '/Schema::create\(\s*(?:\'([^\']+)\'|"([^"]+)"|self::(\w+)|\$(\w+)|Config::(\w+)\(\))\s*,/',
                $src,
                $matches,
                PREG_SET_ORDER | PREG_OFFSET_CAPTURE,
            )) {
                continue;
            }

            foreach ($matches as $match) {
                $tableName = null;
                $notes = '';

                if ($match[1][1] !== -1 && $match[1][0] !== '') {
                    $tableName = $match[1][0]; // строковый литерал
                } elseif ($match[2][1] !== -1 && $match[2][0] !== '') {
                    $tableName = $match[2][0]; // двойные кавычки
                } elseif ($match[3][1] !== -1 && $match[3][0] !== '') {
                    $constName = $match[3][0];

                    if (isset($consts[$constName])) {
                        $tableName = $consts[$constName];
                        $notes = 'table-name-from-const:'.$constName;
                    }
                } elseif ($match[4][1] !== -1 && $match[4][0] !== '') {
                    $varName = $match[4][0];

                    if (isset($varMap[$varName])) {
                        $tableName = $varMap[$varName];
                        $notes = 'table-name-via-config-default';
                    }
                } elseif ($match[5][1] !== -1 && $match[5][0] !== '') {
                    $method = $match[5][0];

                    if (isset($this->configDefaults[$method])) {
                        $tableName = $this->configDefaults[$method];
                        $notes = 'table-name-via-config-default';
                    }
                }

                if ($tableName === null) {
                    continue;
                }

                // Извлечь тело create-блока
                $offset = $match[0][1];
                $body = $this->extractCreateBody($src, $offset);

                $piiClass = $this->detectPiiClass($body);
                $indexes = $this->extractIndexes($body);
                $crossAccess = in_array($tableName, self::CROSS_PACKAGE_EXCEPTIONS, true)
                    ? 'exception'
                    : 'forbidden';
                $specialNotes = in_array($tableName, self::CROSS_PACKAGE_EXCEPTIONS, true)
                    ? 'D102 cross-package FK exception (DATA-004)'
                    : '';

                $allNotes = implode('; ', array_filter([$notes, $specialNotes]));

                $objectClass = $this->detectObjectClass($tableName);

                $tables[] = [
                    'table' => $tableName,
                    'owning_package' => $ownerPkg,
                    'migration' => $relPath,
                    'writers' => '', // обогащается после
                    'readers' => '', // обогащается после
                    'tenant_scope' => $tenantScope,
                    'restore_policy' => 'unobserved',
                    'retention_policy' => 'unobserved',
                    'pii_class' => $piiClass,
                    'cross_package_access' => $crossAccess,
                    'indexes' => $indexes,
                    'upgrade_strategy' => 'expand-migrate-contract (policy-default)',
                    'object_class' => $objectClass,
                    'notes' => $allNotes,
                ];
            }
        }
    }

    /** Извлечь тело Schema::create (до закрывающей }); на том же уровне). */
    private function extractCreateBody(string $src, int $offset): string
    {
        $start = strpos($src, '{', $offset);

        if ($start === false) {
            return '';
        }
        $depth = 0;
        $len = strlen($src);
        $end = $start;
        for ($i = $start; $i < $len; $i++) {
            if ($src[$i] === '{') {
                $depth++;
            } elseif ($src[$i] === '}') {
                $depth--;

                if ($depth === 0) {
                    $end = $i;

                    break;
                }
            }
        }

        return substr($src, $start, $end - $start + 1);
    }

    private function detectPiiClass(string $body): string
    {
        $lbody = strtolower($body);
        $found = [];
        foreach (self::PII_CREDENTIALS as $col) {
            if (str_contains($lbody, "'{$col}'") || str_contains($lbody, "\"{$col}\"")) {
                $found[] = 'credentials';

                break;
            }
        }
        foreach (self::PII_DIRECT as $col) {
            if (str_contains($lbody, "'{$col}'") || str_contains($lbody, "\"{$col}\"")) {
                $found[] = 'pii-direct';

                break;
            }
        }
        foreach (self::PII_INDIRECT as $col) {
            if (str_contains($lbody, "'{$col}'") || str_contains($lbody, "\"{$col}\"")) {
                $found[] = 'pii-indirect';

                break;
            }
        }

        if (in_array('pii-direct', $found, true)) {
            return 'pii-direct';
        }

        if (in_array('credentials', $found, true)) {
            return 'credentials';
        }

        if (in_array('pii-indirect', $found, true)) {
            return 'pii-indirect';
        }

        return 'unobserved';
    }

    private function extractIndexes(string $body): string
    {
        $indexes = [];

        // ->index([...], 'name') или ->index('col')
        if (preg_match_all('/->index\(([^;]+)\)/', $body, $m)) {
            foreach ($m[1] as $args) {
                $indexes[] = 'index('.trim($args).')';
            }
        }

        if (preg_match_all('/->unique\(([^;]+)\)/', $body, $m)) {
            foreach ($m[1] as $args) {
                $indexes[] = 'unique('.trim($args).')';
            }
        }

        if (preg_match_all('/->primary\(([^;]+)\)/', $body, $m)) {
            foreach ($m[1] as $args) {
                $indexes[] = 'primary('.trim($args).')';
            }
        }

        return implode('; ', $indexes);
    }

    private function detectObjectClass(string $table): string
    {
        if (str_contains($table, 'outbox') || str_contains($table, 'pubsub')) {
            return 'outbox';
        }

        if (str_contains($table, 'saved_view') || str_contains($table, 'snapshot')) {
            return 'projection';
        }

        return 'private';
    }

    private function resolveTenantScope(string $filePath): string
    {
        if (str_contains($filePath, '/migrations/root/')) {
            return 'root';
        }

        if (str_contains($filePath, '/migrations/tenant')) {
            return 'tenant';
        }

        return 'tenant';
    }

    private function loadPackageDirs(): void
    {
        foreach (glob($this->repoRoot.'/packages/*/composer.json') ?: [] as $composerFile) {
            $data = json_decode(file_get_contents($composerFile) ?: '{}', true);

            if (isset($data['name'])) {
                $pkgDir = dirname($composerFile);
                $this->packageDirs[$pkgDir] = $data['name'];
            }
        }
    }

    private function loadConfigDefaults(): void
    {
        // Разобрать packages/core/src/Support/Config.php для дефолтов config('...', 'default')
        $configPath = $this->repoRoot.'/packages/core/src/Support/Config.php';

        if (! file_exists($configPath)) {
            return;
        }
        $src = file_get_contents($configPath);

        if ($src === false) {
            return;
        }

        // public static function xxxTable(): string { return (string) config('...', 'default_value'); }
        if (preg_match_all(
            '/public\s+static\s+function\s+(\w+Table)\(\)[^{]*\{[^}]*config\([^,]+,\s*\'([^\']+)\'\)[^}]*\}/',
            $src,
            $m,
            PREG_SET_ORDER,
        )) {
            foreach ($m as $match) {
                $this->configDefaults[$match[1]] = $match[2];
            }
        }
    }

    private function loadFileCsvTableRefs(): void
    {
        $csvPath = $this->planRoot.'/artifacts/architecture/files.csv';

        if (! file_exists($csvPath)) {
            return;
        }
        $fh = fopen($csvPath, 'r');

        if ($fh === false) {
            return;
        }
        $headers = fgetcsv($fh);

        if ($headers === false) {
            fclose($fh);

            return;
        }
        $headers = array_map('trim', $headers);
        $pkgIdx = array_search('package', $headers, true);
        $tablesIdx = array_search('tables', $headers, true);

        if ($pkgIdx === false || $tablesIdx === false) {
            fclose($fh);

            return;
        }

        while (($row = fgetcsv($fh)) !== false) {
            $pkg = $row[$pkgIdx] ?? '';
            $tablesCell = $row[$tablesIdx] ?? '';

            if ($tablesCell === '') {
                continue;
            }
            foreach (explode(';', $tablesCell) as $tbl) {
                $tbl = trim($tbl);

                if ($tbl === '') {
                    continue;
                }

                // tables в files.csv — из Schema::create в src, не в migrations
                // Здесь мы ищем кто читает/пишет — используем как writers
                if (! isset($this->tableWriters[$tbl])) {
                    $this->tableWriters[$tbl] = [];
                }

                if ($pkg !== '' && ! in_array($pkg, $this->tableWriters[$tbl], true)) {
                    $this->tableWriters[$tbl][] = $pkg;
                }
            }
        }
        fclose($fh);
    }
}
