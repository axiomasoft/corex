<?php

declare(strict_types=1);

final class InventoryScanner
{
    public const FILE_HEADERS = [
        'package', 'file', 'namespace', 'symbol', 'symbol_kind', 'visibility',
        'public_api', 'zone_or_context', 'layer', 'framework_dependency',
        'cross_package_imports', 'config_reads', 'facades', 'tables',
        'events_emitted', 'events_consumed', 'crosses_durable_boundary',
        'suggested_namespace', 'suggested_name', 'risk', 'notes',
    ];

    public const SYMBOL_HEADERS = [
        ...self::FILE_HEADERS,
        'source_line', 'certainty', 'phpdoc_internal', 'phpdoc_api',
    ];

    /** @var list<string> */
    private const LAYER_SEGMENTS = [
        'Contracts', 'Data', 'Enums', 'Events', 'Exceptions', 'Models', 'Concerns',
        'Internal', 'Support', 'Console', 'Commands', 'Http', 'Providers',
        'Testing', 'PHPStan', 'Extend', 'root', 'other',
    ];

    /** @var list<string> */
    private const FACADE_NAMES = ['DB', 'Auth', 'Cache', 'Event', 'Log', 'Storage', 'Bus', 'Gate'];

    /** @var list<string> */
    private const DURABLE_PATH_TOKENS = ['Wakeup', 'Outbox', 'Inbox', 'Checkpoint', 'Bulk', 'Cursor'];

    /**
     * @var array<string, array{name: string, dir: string, require: list<string>, psr4: array<string, string>, public_api: ?list<string>}>
     */
    private array $packages = [];

    public function __construct(private readonly string $repoRoot) {}

    /**
     * @return list<array<string, string>>
     */
    public function scanSrc(): array
    {
        $this->loadPackages();

        $rows = [];
        foreach ($this->packages as $meta) {
            $srcDir = $meta['dir'].'/src';

            if (! is_dir($srcDir)) {
                continue;
            }
            $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($srcDir));
            foreach ($it as $file) {
                if (! $file->isFile() || ! str_ends_with($file->getFilename(), '.php')) {
                    continue;
                }
                $relative = $this->relativePath($file->getPathname());
                foreach ($this->scanFile($relative, $meta) as $row) {
                    $rows[] = $row;
                }
            }
        }

        return $rows;
    }

    /**
     * @return array<string, string> alias => FQCN (class imports only)
     */
    public static function parseUseFromSource(string $source): array
    {
        $tokens = token_get_all($source, TOKEN_PARSE);
        $aliases = [];
        for ($i = 0; $i < count($tokens); $i++) {
            if (! is_array($tokens[$i]) || $tokens[$i][0] !== T_USE) {
                continue;
            }

            if (self::isClosureUse($tokens, $i)) {
                continue;
            }
            $parsed = self::parseUseStatement($tokens, $i);
            foreach ($parsed['class'] as $alias => $fqcn) {
                $aliases[$alias] = $fqcn;
            }
        }

        return $aliases;
    }

    /**
     * @param  array<int, array{0: int, 1: string, 2: int}|string>  $tokens
     * @return array{class: array<string, string>, notes: list<string>}
     */
    public static function parseUseStatement(array $tokens, int $useIndex): array
    {
        $class = [];
        $notes = [];
        $i = $useIndex + 1;
        $i = self::skipWhitespace($tokens, $i);

        if (self::isToken($tokens, $i, T_FUNCTION) || self::isToken($tokens, $i, T_CONST)) {
            return ['class' => [], 'notes' => []];
        }

        while ($i < count($tokens)) {
            $group = self::parseOneUseClause($tokens, $i, $notes);
            $class = array_merge($class, $group);
            $i = self::skipWhitespace($tokens, $i);

            if (self::tokenText($tokens, $i) === ',') {
                $i++;

                continue;
            }

            if (self::tokenText($tokens, $i) === ';') {
                break;
            }

            break;
        }

        return ['class' => $class, 'notes' => $notes];
    }

    /**
     * @param  array{name: string, dir: string, require: list<string>, psr4: array<string, string>, public_api: ?list<string>}  $meta
     * @return list<array<string, string>>
     */
    private function scanFile(string $relativeFile, array $meta): array
    {
        $absolute = $this->repoRoot.'/'.$relativeFile;
        $content = file_get_contents($absolute);

        if ($content === false) {
            return [];
        }

        $tokens = token_get_all($content, TOKEN_PARSE);
        $namespace = $this->parseNamespace($tokens);
        $useParsed = self::collectUseTable($tokens);
        $types = $this->extractTypes($tokens, $namespace);
        $fileContext = $this->buildFileContext(
            $relativeFile,
            $meta,
            $namespace,
            $content,
            $tokens,
            $useParsed,
        );

        if ($types === []) {
            return [$this->finalizeRow($fileContext, [
                'symbol' => '—',
                'symbol_kind' => 'file',
                'source_line' => '1',
                'certainty' => 'static',
                'phpdoc_internal' => 'no',
                'phpdoc_api' => 'no',
            ])];
        }

        $rows = [];
        foreach ($types as $type) {
            $rows[] = $this->finalizeRow($fileContext, $type);
        }

        return $rows;
    }

    /**
     * @param  array<string, mixed>  $fileContext
     * @param  array<string, string>  $typeFields
     * @return array<string, string>
     */
    private function finalizeRow(array $fileContext, array $typeFields): array
    {
        $row = array_merge($fileContext, $typeFields);
        foreach (self::SYMBOL_HEADERS as $header) {
            $row[$header] ??= '';
        }

        return $row;
    }

    /**
     * @param  array{class: array<string, string>, notes: list<string>}  $useParsed
     * @param  array{name: string, dir: string, require: list<string>, psr4: array<string, string>, public_api: ?list<string>}  $meta
     * @return array<string, string>
     */
    private function buildFileContext(
        string $relativeFile,
        array $meta,
        string $namespace,
        string $content,
        array $tokens,
        array $useParsed,
    ): array {
        $zone = $this->zoneFromPath($relativeFile);
        $layer = $this->layerFromPath($relativeFile);
        $visibility = str_contains($namespace, '\\Internal\\') || str_ends_with($namespace, '\\Internal')
            ? 'internal-ns'
            : 'public';
        $publicApi = $this->publicApiStatus($namespace, $meta['public_api']);
        $framework = $this->frameworkImports($useParsed['class']);
        $crossPackage = $this->crossPackageImports($useParsed['class'], $meta['name']);
        $configReads = $this->extractConfigReads($content);
        $facades = $this->extractFacades($content);
        $tables = $this->extractTables($content);
        $emitted = $this->extractEventsEmitted($content);
        $consumed = $this->extractEventsConsumed($content);
        $durable = $this->crossesDurableBoundary($content, $relativeFile, $layer, $useParsed['class']);
        $notes = array_unique(array_merge($useParsed['notes'], $this->extraNotes($content, $tokens)));
        sort($notes);
        $risk = $this->computeRisk(
            $meta,
            $relativeFile,
            $crossPackage,
            $configReads,
            $facades,
            $durable,
            $content,
        );

        return [
            'package' => $meta['name'],
            'file' => $relativeFile,
            'namespace' => $namespace,
            'visibility' => $visibility,
            'public_api' => $publicApi,
            'zone_or_context' => $zone,
            'layer' => $layer,
            'framework_dependency' => $this->joinList($framework),
            'cross_package_imports' => $this->joinList($crossPackage),
            'config_reads' => $this->joinList($configReads),
            'facades' => $this->joinList($facades),
            'tables' => $this->joinList($tables),
            'events_emitted' => $this->joinList($emitted),
            'events_consumed' => $this->joinList($consumed),
            'crosses_durable_boundary' => $durable,
            'suggested_namespace' => '—',
            'suggested_name' => '—',
            'risk' => $risk,
            'notes' => implode('; ', $notes),
        ];
    }

    /**
     * @param  array<int, array{0: int, 1: string, 2: int}|string>  $tokens
     * @return array{class: array<string, string>, notes: list<string>}
     */
    private static function collectUseTable(array $tokens): array
    {
        $class = [];
        $notes = [];
        for ($i = 0; $i < count($tokens); $i++) {
            if (! is_array($tokens[$i]) || $tokens[$i][0] !== T_USE) {
                continue;
            }

            if (self::isClosureUse($tokens, $i)) {
                continue;
            }
            $parsed = self::parseUseStatement($tokens, $i);
            $class = array_merge($class, $parsed['class']);
            $notes = array_merge($notes, $parsed['notes']);
        }

        return ['class' => $class, 'notes' => $notes];
    }

    /**
     * @param  array<int, array{0: int, 1: string, 2: int}|string>  $tokens
     * @return list<array<string, string>>
     */
    private function extractTypes(array $tokens, string $namespace): array
    {
        $types = [];
        $depth = 0;
        $pendingDoc = '';

        for ($i = 0; $i < count($tokens); $i++) {
            $token = $tokens[$i];

            if (is_string($token)) {
                if ($token === '{') {
                    $depth++;
                } elseif ($token === '}') {
                    $depth--;
                }

                continue;
            }

            if ($token[0] === T_DOC_COMMENT && $depth === 0) {
                $pendingDoc = $token[1];

                continue;
            }

            if ($depth !== 0) {
                continue;
            }

            $kind = match ($token[0]) {
                T_CLASS => 'class',
                T_INTERFACE => 'interface',
                T_TRAIT => 'trait',
                T_ENUM => 'enum',
                default => null,
            };

            if ($kind === null) {
                continue;
            }

            $j = self::skipWhitespace($tokens, $i + 1);
            while (self::isModifier($tokens, $j)) {
                $j++;
                $j = self::skipWhitespace($tokens, $j);
            }

            $name = self::tokenText($tokens, $j);

            if ($name === null) {
                $pendingDoc = '';

                continue;
            }

            $line = $token[2];
            $internal = str_contains($pendingDoc, '@internal') ? 'yes' : 'no';
            $api = str_contains($pendingDoc, '@api') ? 'yes' : 'no';
            $types[] = [
                'symbol' => $name,
                'symbol_kind' => $kind,
                'source_line' => (string) $line,
                'certainty' => 'static',
                'phpdoc_internal' => $internal,
                'phpdoc_api' => $api,
            ];
            $pendingDoc = '';
        }

        return $types;
    }

    /**
     * @param  array<int, array{0: int, 1: string, 2: int}|string>  $tokens
     */
    private function parseNamespace(array $tokens): string
    {
        $namespace = '';
        for ($i = 0; $i < count($tokens); $i++) {
            if (! is_array($tokens[$i]) || $tokens[$i][0] !== T_NAMESPACE) {
                continue;
            }
            $j = $i + 1;
            $j = self::skipWhitespace($tokens, $j);

            if (self::tokenText($tokens, $j) === '{') {
                $parts = [];
                $j++;
                while ($j < count($tokens)) {
                    if (self::tokenText($tokens, $j) === '}') {
                        break;
                    }
                    $text = self::tokenText($tokens, $j);

                    if ($text !== null && $text !== ';') {
                        $parts[] = $text;
                    }
                    $j++;
                }
                $namespace = implode('', $parts);

                break;
            }

            $parts = [];
            while ($j < count($tokens)) {
                $text = self::tokenText($tokens, $j);

                if ($text === ';' || $text === '{') {
                    break;
                }

                if ($text !== null) {
                    $parts[] = $text;
                }
                $j++;
            }
            $namespace = implode('', $parts);

            break;
        }

        return rtrim($namespace, '\\');
    }

    /**
     * @param  array<int, array{0: int, 1: string, 2: int}|string>  $tokens
     * @param  list<string>  $notes
     * @return array<string, string>
     */
    private static function parseOneUseClause(array $tokens, int &$i, array &$notes): array
    {
        $i = self::skipWhitespace($tokens, $i);
        $prefix = self::readQualifiedName($tokens, $i);
        $i = self::skipWhitespace($tokens, $i);

        $next = self::tokenText($tokens, $i);

        if ($next === ';' || $next === ',') {
            $fqcn = ltrim($prefix, '\\');
            $short = self::shortClassName($fqcn);

            return [$short => $fqcn];
        }

        if (self::tokenText($tokens, $i) === '{') {
            $notes[] = 'group-use';
            $i++;
            $imports = [];
            while ($i < count($tokens) && self::tokenText($tokens, $i) !== '}') {
                $i = self::skipWhitespace($tokens, $i);

                if (self::tokenText($tokens, $i) === '}') {
                    break;
                }
                $imports = array_merge($imports, self::readUseEntry($tokens, $i, $prefix));
                $i = self::skipWhitespace($tokens, $i);

                if (self::tokenText($tokens, $i) === ',') {
                    $i++;
                }
            }

            if (self::tokenText($tokens, $i) === '}') {
                $i++;
            }

            return $imports;
        }

        return self::readUseEntry($tokens, $i, $prefix);
    }

    /**
     * @param  array<int, array{0: int, 1: string, 2: int}|string>  $tokens
     * @return array<string, string>
     */
    private static function readUseEntry(array $tokens, int &$i, string $prefix): array
    {
        $nameParts = [];
        while ($i < count($tokens)) {
            if (self::isToken($tokens, $i, T_WHITESPACE)) {
                $i++;

                continue;
            }
            $text = self::tokenText($tokens, $i);

            if ($text === null) {
                $i++;

                continue;
            }

            if ($text === ',' || $text === ';' || $text === '}') {
                break;
            }

            if ($text === 'as') {
                $i++;
                $i = self::skipWhitespace($tokens, $i);
                $alias = self::tokenText($tokens, $i);
                $short = implode('', $nameParts);
                $fqcn = self::combineFqcn($prefix, $short);
                $i++;

                return [$alias ?? self::shortClassName($fqcn) => $fqcn];
            }
            $nameParts[] = $text;
            $i++;
        }

        $short = implode('', $nameParts);

        if ($short === '') {
            return [];
        }

        $fqcn = self::combineFqcn($prefix, $short);

        return [$short => $fqcn];
    }

    private static function combineFqcn(string $prefix, string $short): string
    {
        $prefix = rtrim($prefix, '\\');

        if ($prefix === '') {
            return ltrim($short, '\\');
        }

        if ($short === '') {
            return ltrim($prefix, '\\');
        }

        return ltrim($prefix.'\\'.$short, '\\');
    }

    /**
     * @param  array<int, array{0: int, 1: string, 2: int}|string>  $tokens
     */
    private static function readQualifiedName(array $tokens, int &$i): string
    {
        $parts = [];
        while ($i < count($tokens)) {
            if (self::isToken($tokens, $i, T_WHITESPACE)) {
                $i++;

                continue;
            }
            $text = self::tokenText($tokens, $i);

            if ($text === null) {
                $i++;

                continue;
            }

            if ($text === '{' || $text === ',' || $text === ';' || $text === 'as') {
                break;
            }
            $parts[] = $text;
            $i++;
        }

        return implode('', $parts);
    }

    /**
     * @param  array<int, array{0: int, 1: string, 2: int}|string>  $tokens
     */
    private static function skipWhitespace(array $tokens, int $i): int
    {
        while ($i < count($tokens) && is_array($tokens[$i]) && $tokens[$i][0] === T_WHITESPACE) {
            $i++;
        }

        return $i;
    }

    /**
     * @param  array<int, array{0: int, 1: string, 2: int}|string>  $tokens
     */
    private static function tokenText(array $tokens, int $i): ?string
    {
        if (! isset($tokens[$i])) {
            return null;
        }

        if (is_string($tokens[$i])) {
            return $tokens[$i];
        }

        return $tokens[$i][1];
    }

    /**
     * @param  array<int, array{0: int, 1: string, 2: int}|string>  $tokens
     */
    private static function isToken(array $tokens, int $i, int $type): bool
    {
        return isset($tokens[$i]) && is_array($tokens[$i]) && $tokens[$i][0] === $type;
    }

    /**
     * @param  array<int, array{0: int, 1: string, 2: int}|string>  $tokens
     */
    private static function isModifier(array $tokens, int $i): bool
    {
        if (! isset($tokens[$i]) || ! is_array($tokens[$i])) {
            return false;
        }

        return in_array($tokens[$i][0], [T_ABSTRACT, T_FINAL, T_READONLY], true);
    }

    /**
     * @param  array<int, array{0: int, 1: string, 2: int}|string>  $tokens
     */
    private static function isClosureUse(array $tokens, int $useIndex): bool
    {
        $j = self::skipWhitespace($tokens, $useIndex + 1);

        return self::tokenText($tokens, $j) === '(';
    }

    private function loadPackages(): void
    {
        $this->packages = [];
        foreach (glob($this->repoRoot.'/packages/*/composer.json') ?: [] as $composerPath) {
            $json = json_decode((string) file_get_contents($composerPath), true);

            if (! is_array($json) || ! isset($json['name'])) {
                continue;
            }
            $publicApi = null;

            if (isset($json['extra']['corex']['public-api']) && is_array($json['extra']['corex']['public-api'])) {
                $publicApi = array_values($json['extra']['corex']['public-api']);
            }
            $this->packages[$json['name']] = [
                'name' => $json['name'],
                'dir' => dirname($composerPath),
                'require' => array_keys($json['require'] ?? []),
                'psr4' => $json['autoload']['psr-4'] ?? [],
                'public_api' => $publicApi,
            ];
        }
    }

    private function relativePath(string $absolute): string
    {
        $prefix = rtrim($this->repoRoot, '/').'/';

        return str_starts_with($absolute, $prefix)
            ? substr($absolute, strlen($prefix))
            : $absolute;
    }

    private function zoneFromPath(string $relativeFile): string
    {
        if (! preg_match('#^packages/[^/]+/src(?:/(.+))?$#', $relativeFile, $m)) {
            return 'root';
        }

        if (! isset($m[1]) || $m[1] === '') {
            return 'root';
        }
        $segment = explode('/', $m[1])[0];

        return $segment === basename($m[1]) && ! str_contains($m[1], '/')
            ? 'root'
            : $segment;
    }

    private function layerFromPath(string $relativeFile): string
    {
        if (! preg_match('#^packages/[^/]+/src(?:/(.*))?$#', $relativeFile, $m)) {
            return 'other';
        }

        if (! isset($m[1]) || $m[1] === '' || ! str_contains($m[1], '/')) {
            return 'root';
        }
        $segment = explode('/', $m[1])[0];

        if (in_array($segment, self::LAYER_SEGMENTS, true)) {
            return $segment;
        }

        return 'other';
    }

    /**
     * @param  ?list<string>  $publicApiPrefixes
     */
    private function publicApiStatus(string $namespace, ?array $publicApiPrefixes): string
    {
        if ($publicApiPrefixes === null) {
            return 'unknown';
        }
        foreach ($publicApiPrefixes as $prefix) {
            if ($namespace === $prefix || str_starts_with($namespace.'\\', rtrim($prefix, '\\').'\\')) {
                return 'yes';
            }
        }

        return 'no';
    }

    /**
     * @param  array<string, string>  $useTable
     * @return list<string>
     */
    private function frameworkImports(array $useTable): array
    {
        $found = [];
        foreach ($useTable as $fqcn) {
            if (str_starts_with($fqcn, 'Illuminate\\') || str_starts_with($fqcn, 'Laravel\\')) {
                $found[] = $fqcn;
            }
        }
        sort($found);

        return array_values(array_unique($found));
    }

    /**
     * @param  array<string, string>  $useTable
     * @return list<string>
     */
    private function crossPackageImports(array $useTable, string $fromPackage): array
    {
        $imports = [];
        foreach ($useTable as $fqcn) {
            $owner = $this->resolveOwner($fqcn);

            if ($owner === null || $owner === $fromPackage) {
                continue;
            }
            $imports[] = $fqcn;
        }
        sort($imports);

        return array_values(array_unique($imports));
    }

    private function resolveOwner(string $import): ?string
    {
        $bestPrefix = '';
        $owner = null;
        foreach ($this->packages as $meta) {
            foreach ($meta['psr4'] as $prefix => $_dir) {
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
     * @return list<string>
     */
    private function extractConfigReads(string $content): array
    {
        $keys = [];

        if (preg_match_all("/\bconfig\s*\(\s*['\"]([^'\"]+)['\"]/", $content, $m)) {
            $keys = $m[1];
        }
        sort($keys);

        return array_values(array_unique($keys));
    }

    /**
     * @return list<string>
     */
    private function extractFacades(string $content): array
    {
        $found = [];
        foreach (self::FACADE_NAMES as $name) {
            if (preg_match('/\b'.$name.'::/', $content) === 1) {
                $found[] = $name;
            }
        }

        return $found;
    }

    /**
     * @return list<string>
     */
    private function extractTables(string $content): array
    {
        $tables = [];

        if (preg_match_all("/Schema::create\s*\(\s*['\"]([^'\"]+)['\"]/", $content, $m)) {
            $tables = array_merge($tables, $m[1]);
        }

        if (preg_match_all("/->setTable\s*\(\s*['\"]([^'\"]+)['\"]/", $content, $m)) {
            $tables = array_merge($tables, $m[1]);
        }

        if (preg_match_all('/\$table\s*=\s*[\'"]([^\'"]+)[\'"]/', $content, $m)) {
            $tables = array_merge($tables, $m[1]);
        }
        sort($tables);

        return array_values(array_unique($tables));
    }

    /**
     * @return list<string>
     */
    private function extractEventsEmitted(string $content): array
    {
        $events = [];

        if (preg_match_all('/\bevent\s*\(\s*([A-Za-z0-9_\\\\]+)/', $content, $m)) {
            foreach ($m[1] as $name) {
                if (str_ends_with($name, 'Event')) {
                    $events[] = $name;
                }
            }
        }

        if (preg_match_all('/Event::dispatch\s*\(\s*([A-Za-z0-9_\\\\]+)/', $content, $m)) {
            foreach ($m[1] as $name) {
                if (str_ends_with($name, 'Event')) {
                    $events[] = $name;
                }
            }
        }
        sort($events);

        return array_values(array_unique($events));
    }

    /**
     * @return list<string>
     */
    private function extractEventsConsumed(string $content): array
    {
        $events = [];

        if (preg_match_all('/Event::listen\s*\(\s*([A-Za-z0-9_\\\\]+)/', $content, $m)) {
            $events = array_merge($events, $m[1]);
        }

        if (preg_match_all('/#\[ListensTo\s*\(\s*([A-Za-z0-9_\\\\:]+)/', $content, $m)) {
            $events = array_merge($events, $m[1]);
        }
        sort($events);

        return array_values(array_unique($events));
    }

    /**
     * @param  array<string, string>  $useTable
     */
    private function crossesDurableBoundary(string $content, string $relativeFile, string $layer, array $useTable): string
    {
        if (preg_match('/\bimplements\s+[^;{]*\bShouldQueue\b/', $content) === 1) {
            return 'yes';
        }

        if (str_contains($content, 'SerializesModels')) {
            return 'yes';
        }

        if (preg_match('/\bimplements\s+[^;{]*\bDurablePayload\b/', $content) === 1) {
            return 'yes';
        }

        foreach (self::DURABLE_PATH_TOKENS as $token) {
            if (str_contains($relativeFile, $token) && in_array($layer, ['Data', 'Internal'], true)) {
                return 'yes';
            }
        }

        $importsDataScope = false;
        foreach ($useTable as $fqcn) {
            if (str_ends_with($fqcn, 'DataScope') || str_contains($fqcn, '\\DataScope')) {
                $importsDataScope = true;

                break;
            }
        }

        if ($importsDataScope && preg_match('/DataScope/', $content) === 1) {
            if (preg_match('/(json_encode|toArray|queue|payload)/', $content) === 1) {
                return 'unresolved';
            }
        }

        return 'no';
    }

    /**
     * @param  array<int, array{0: int, 1: string, 2: int}|string>  $tokens
     * @return list<string>
     */
    private function extraNotes(string $content, array $tokens): array
    {
        $notes = [];

        if (preg_match('/\$[a-zA-Z_][a-zA-Z0-9_]*\s*::class/', $content) === 1) {
            $notes[] = 'dynamic-name';
        }

        return $notes;
    }

    /**
     * @param  array{name: string, dir: string, require: list<string>, psr4: array<string, string>, public_api: ?list<string>}  $meta
     * @param  list<string>  $crossPackage
     * @param  list<string>  $configReads
     * @param  list<string>  $facades
     */
    private function computeRisk(
        array $meta,
        string $relativeFile,
        array $crossPackage,
        array $configReads,
        array $facades,
        string $durable,
        string $content,
    ): string {
        $signals = [];

        foreach ($crossPackage as $fqcn) {
            $owner = $this->resolveOwner($fqcn);

            if ($owner !== null && ! in_array($owner, $meta['require'], true)) {
                $signals['hidden-dep'] = true;
            }
        }

        $isProvider = str_ends_with($relativeFile, 'ServiceProvider.php');
        $isCommand = str_contains($relativeFile, '/Commands/');

        if ($configReads !== [] && ! $isProvider && ! $isCommand) {
            $signals['config'] = true;
        }

        $composition = $isProvider || $isCommand;

        if ($facades !== [] && ! $composition) {
            $signals['facade'] = true;
        }

        if ($durable === 'yes') {
            $signals['durable'] = true;
        }

        if (preg_match('/\bnow\s*\(/', $content) === 1) {
            $signals['clock'] = true;
        }

        if (count($signals) === 0) {
            return 'none';
        }

        if (count($signals) > 1) {
            return 'mixed';
        }

        return array_key_first($signals);
    }

    /**
     * @param  list<string>  $items
     */
    private function joinList(array $items): string
    {
        return implode(';', $items);
    }

    private static function shortClassName(string $fqcn): string
    {
        $pos = strrpos($fqcn, '\\');

        return $pos === false ? $fqcn : substr($fqcn, $pos + 1);
    }
}
