#!/usr/bin/env php
<?php

declare(strict_types=1);

use CoreX\Operations\OperationsServiceProvider;
use Orchestra\Testbench\Foundation\Application;
use Packman\Schema\Contracts\Migration\SchemaSets;

/**
 * Shipped schema S1–S9. Tokenization never executes a migration or connects to a database.
 * The manifest is projected from the actual Testbench registry only for --write-sets/--check.
 *
 * @phpstan-type SchemaManifest array{packages: array<string, string>, sets: list<array{id: string, package: string, name: string, scope: string, path: string, version: int, drivers: list<string>, lifecycle: string, requires: list<string>, prerequisiteSets: list<string>, connection: ?string}>}
 * @phpstan-type SchemaHit array{code: string, file: string, line: int, message: string}
 * @phpstan-type SchemaCall array{name: string, args: list<string>, offset: int, end: int, line: int, method: bool}
 */
final class SchemaGate
{
    private const array VERSION_THREE_TENANT_PATHS = [
        'axiomasoft/corex:tenant' => 'packages/core/database/migrations/tenant',
        'axiomasoft/corex-import-export:tenant' => 'packages/import-export/database/migrations/tenant',
        'axiomasoft/corex-media:tenant' => 'packages/media/database/migrations/tenant',
        'axiomasoft/corex-operations:tenant' => 'packages/operations/database/migrations/tenant',
    ];

    /** @var list<SchemaHit> */
    private array $hits = [];

    /** @var array<string, string> */
    private array $objects = [];

    /** @var array<string, array{owner: string, scope: string}> */
    private array $catalogue = [];

    /** @param SchemaManifest $manifest
     * @param array<string, string> $allowlist */
    public function __construct(private readonly string $root, private array $manifest, private array $allowlist)
    {
        $path = $root.'/docs/architecture/table-ownership.md';

        if (! is_file($path)) {
            throw new RuntimeException('Missing table ownership catalogue.');
        }
        foreach (self::lines($path) as $row) {
            if (preg_match('~^\| ([a-z0-9_]+) \| ([a-z0-9-]+/[a-z0-9-]+) \| ([a-z0-9-]+) \|~', $row, $match) === 1) {
                $this->catalogue[$match[1]] = ['owner' => $match[2], 'scope' => $match[3]];
            }
        }
    }

    /** @return list<SchemaHit> */
    public function run(): array
    {
        $this->hits = [];
        $this->objects = [];
        $seen = [];
        foreach ($this->manifest['sets'] as $set) {
            $path = $this->root.'/'.$set['path'];

            if (! is_dir($path) || self::files($path.'/*.php') === []) {
                $this->hit('S1', $set['path'], 1, 'registered set has no migration files');
            }

            $baselineVersion = $set['path'] === (self::VERSION_THREE_TENANT_PATHS[$set['id']] ?? null)
                ? 3
                : (str_starts_with($set['path'], 'packages/') ? 2 : 1);

            if ($set['version'] !== $baselineVersion) {
                $this->hit('S8', $set['path'], 1, 'baseline set version must be '.$baselineVersion);
            }
        }
        foreach (['packages', 'products'] as $area) {
            foreach (self::files($this->root.'/'.$area.'/*/database/migrations', GLOB_ONLYDIR) as $directory) {
                $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS));
                foreach ($iterator as $file) {
                    if (! $file instanceof SplFileInfo) {
                        throw new RuntimeException('Invalid migration directory entry.');
                    }

                    if (! $file->isFile() || $file->getExtension() !== 'php' || str_contains($file->getPathname(), '/tests/')) {
                        continue;
                    }
                    $relative = substr($file->getPathname(), strlen($this->root) + 1);
                    $sets = array_values(array_filter($this->manifest['sets'], static fn (array $set): bool => dirname($relative) === $set['path']));

                    if (count($sets) !== 1) {
                        $this->hit('S1', $relative, 1, 'file must belong to exactly one registered set directory');

                        continue;
                    }
                    $set = $sets[0];
                    $basename = basename($relative);

                    if (isset($seen[$basename])) {
                        $this->hit('S2', $relative, 1, 'duplicate basename: '.$seen[$basename]);
                    }
                    $seen[$basename] = $relative;

                    if (preg_match('/^\d{4}_\d{2}_\d{2}_(\d{2})\d{4}_(create|add)_[a-z0-9_]+\.php$/', $basename, $match) !== 1
                        || $match[1] !== ($this->manifest['packages'][$set['package']] ?? null)) {
                        $this->hit('S2', $relative, 1, 'invalid baseline filename or package sequence prefix');
                    }

                    if (! str_starts_with($basename, '2026_10_01_')) {
                        $this->hit('S8', $relative, 1, 'baseline date must be 2026_10_01');
                    }
                    $this->migration($relative, $set['package'], $set['scope']);
                }
            }
        }
        usort($this->hits, static fn (array $a, array $b): int => [$a['code'], $a['file'], $a['line'], $a['message']] <=> [$b['code'], $b['file'], $b['line'], $b['message']]);

        return $this->hits;
    }

    private function migration(string $file, string $package, string $scope): void
    {
        $source = (string) file_get_contents($this->root.'/'.$file);
        $code = self::code($source);
        // Strings and comments containing a method-like token never count as calls.
        $up = self::up($code);

        if ($up === null) {
            $this->hit('S7', $file, 1, 'migration must declare up()');

            return;
        }
        $bodies = [$up];
        $visited = ['up' => true];
        for ($i = 0; $i < count($bodies); $i++) {
            preg_match_all('/(?:\$this\s*->|self\s*::|static\s*::)\s*(\w+)\s*\(/', self::withoutStrings($bodies[$i]['body']), $helpers);
            foreach ($helpers[1] as $helper) {
                if (isset($visited[$helper])) {
                    continue;
                }
                $visited[$helper] = true;
                $body = self::up($code, $helper);

                if ($body !== null) {
                    $bodies[] = $body;
                }
            }
        }
        foreach ($bodies as $body) {
            $this->purity($file, $code, $body['body'], $body['line']);
        }
        $variables = [];
        preg_match_all('/(?:\$(\w+)|const\s+(?:string\s+)?(\w+))\s*=\s*([\'"])([a-z0-9_]+)\3/', $code, $assignments, PREG_SET_ORDER);
        foreach ($assignments as $assignment) {
            $variables[$assignment[1] !== '' ? '$'.$assignment[1] : 'self::'.$assignment[2]] = $assignment[4];
        }
        foreach ($bodies as $body) {
            $this->structure($file, $package, $scope, $body, $variables);
        }
    }

    /** @param array{body: string, line: int} $up
     * @param array<string, string> $variables */
    private function structure(string $file, string $package, string $scope, array $up, array $variables): void
    {
        $calls = self::calls($up['body']);
        $rawMethods = ['statement', 'unprepared', 'select', 'selectOne', 'scalar', 'cursor', 'affectingStatement', 'insert', 'update', 'delete', 'exec', 'query', 'prepare', 'selectFromWriteConnection', 'selectResultSets'];
        $rawCalls = array_values(array_filter($calls, static function (array $call) use ($rawMethods): bool {
            if (! $call['method']) {
                return false;
            }
            $argument = $call['args'][0] ?? '';
            $literal = self::rawSql($argument, []);

            return in_array($call['name'], $rawMethods, true)
                || ($literal !== null && preg_match('/^(?:CREATE|ALTER|DO|CALL|EXECUTE)\s+/i', ltrim($literal)) === 1);
        }));
        $rawVariables = self::rawVariables($up['body']);
        $tables = [];
        foreach ($calls as $call) {
            if (! $call['method'] || ($call['name'] !== 'create' && $call['name'] !== 'table')
                || ($call['name'] === 'table' && ! isset($call['args'][1]))) {
                continue;
            }
            $argument = self::argument($call, 0, 'table') ?? '';
            $table = self::literal($argument) ?? ($variables[$argument] ?? null);

            if ($table === null) {
                $this->hit('S7', $file, $up['line'] + $call['line'] - 1, 'table name must be statically resolvable');

                continue;
            }
            $tables[] = ['name' => $table, 'start' => $call['offset'], 'end' => $call['end']];

            if ($call['name'] === 'create') {
                $this->ownership($file, $up['line'] + $call['line'] - 1, $table, $package, $scope);
            }
        }
        foreach ($calls as $call) {
            $line = $up['line'] + $call['line'] - 1;
            $name = $call['name'];

            if (array_any($rawCalls, static fn (array $raw): bool => $raw['offset'] === $call['offset'])) {
                $sql = self::rawSql(self::argument($call, 0, 'query') ?? self::argument($call, 0, 'sql') ?? '', $rawVariables);

                if ($sql === null) {
                    $this->hit('S6', $file, $line, 'cannot verify computed raw SQL; use explicit column DDL');
                } else {
                    $this->rawIntegers($file, $line, $sql);
                }
            }
            $table = '';
            foreach ($tables as $candidate) {
                if ($call['offset'] > $candidate['start'] && $call['offset'] < $candidate['end']) {
                    $table = $candidate['name'];
                }
            }

            if ($name === 'constrained' || preg_match('/^(?:nullable)?(?:Uuid|Ulid|Numeric)?[mM]orphs$/', $name) === 1) {
                $this->hit('S3', $file, $line, 'implicitly named constraint/index: '.$name);
            }

            if (in_array($name, ['index', 'unique', 'primary', 'foreign', 'foreignId', 'foreignUuid', 'foreignUlid', 'fullText', 'spatialIndex'], true)) {
                // Blueprint APIs take columns then name; a chained column takes only name.
                $references = false;
                $foreignColumn = in_array($name, ['foreignId', 'foreignUuid', 'foreignUlid'], true);
                $before = substr($up['body'], 0, $call['offset']);
                $chained = preg_match('/\)\s*->\s*$/', $before) === 1;
                $object = $call['args'][$chained ? 0 : 1] ?? null;
                foreach ($call['args'] as $argument) {
                    if (preg_match('/^name\s*:\s*(.+)$/s', $argument, $named) === 1) {
                        $object = $named[1];
                    }
                }

                if (($name === 'foreign' || $foreignColumn) && $object === null) {
                    $semicolon = strpos($up['body'], ';', $call['end']);
                    $chain = substr($up['body'], $call['end'], ($semicolon === false ? strlen($up['body']) : $semicolon) - $call['end']);
                    $references = false;
                    foreach (self::calls($chain) as $fluent) {
                        $references = $references || $fluent['name'] === 'references';

                        if ($fluent['name'] === 'name') {
                            $object = $fluent['args'][0] ?? null;
                        }
                    }
                }

                if ($foreignColumn && ! $references) {
                    continue; // A foreignId column alone creates no foreign key.
                }

                if ($object === null || trim($object) === '' || trim($object) === 'null') {
                    $this->hit('S3', $file, $line, 'explicit object name required: '.$name);
                } else {
                    $this->object($file, $line, self::literal($object), $table, $scope);
                }
            }

            if (preg_match('/^(?:unsigned)?(?:Integer|SmallInteger|TinyInteger|MediumInteger)$|^(?:integer|smallInteger|tinyInteger|mediumInteger|increments|smallIncrements|tinyIncrements|mediumIncrements)$/', $name) === 1) {
                $column = self::literal(self::argument($call, 0, 'column') ?? '');

                if ($column === null || ! isset($this->allowlist[$table.'.'.$column])) {
                    $this->hit('S6', $file, $line, '32-bit integer outside allowlist: '.$table.'.'.($column ?? '?'));
                }
            }
        }
        // SQL is inspected only inside strings, never comments or PHP identifiers.
        $offset = -6;
        foreach (token_get_all('<?php '.$up['body']) as $token) {
            $start = $offset;
            $offset += strlen(is_array($token) ? $token[1] : $token);

            if (! is_array($token) || ! in_array($token[0], [T_CONSTANT_ENCAPSED_STRING, T_ENCAPSED_AND_WHITESPACE], true)) {
                continue;
            }
            $sql = trim($token[1], "\"'");
            $line = $up['line'] + $token[2] - 1;

            if (preg_match('/\bCREATE\s+TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?["`]?([a-z0-9_]+)/i', $sql, $match) === 1) {
                $this->ownership($file, $line, $match[1], $package, $scope);
            }

            // Declarations and literals passed to other SQL APIs are checked too.
            // Known raw calls were checked as a whole, including interpolation.
            if (! array_any($rawCalls, static fn (array $call): bool => $start > $call['offset'] && $start < $call['end'])) {
                $this->rawIntegers($file, $line, $token[0] === T_CONSTANT_ENCAPSED_STRING ? (self::rawSql($token[1], []) ?? $sql) : $sql);
            }

            // RENAME references the old object; S4 applies to its new literal name.
            if (preg_match('/\bRENAME\s+CONSTRAINT\s+["`]?([a-z0-9_]+)["`]?\s+TO\s+(["`]?[a-z0-9_]+["`]?)/i', $sql, $rename) === 1) {
                preg_match('/\bALTER\s+TABLE\s+["`]?([a-z0-9_]+)/i', $sql, $target);
                $this->object($file, $line, trim($rename[2], '"`'), $target[1] ?? '', $scope, register: false);
                $sql = str_replace($rename[0], '', $sql);
            } elseif (preg_match('/\bRENAME\s+CONSTRAINT\b/i', $sql) === 1) {
                $this->hit('S4', $file, $line, 'literal constraint rename target required');
            }
            preg_match_all('/\b(?:CONSTRAINT|CREATE\s+(?:UNIQUE\s+)?INDEX(?:\s+CONCURRENTLY)?(?:\s+IF\s+NOT\s+EXISTS)?)\s+["`]?([a-z0-9_]+)["`]?/i', $sql, $names);
            foreach ($names[1] as $object) {
                preg_match('/\b(?:ALTER\s+TABLE|ON|CREATE\s+TABLE)\s+["`]?([a-z0-9_]+)/i', $sql, $target);
                $this->object($file, $line, $object, $target[1] ?? '', $scope);
            }
        }
    }

    /**
     * Only unambiguous identifiers and proven index-only variable templates may
     * feed raw calls. Column SQL variables/computed tails stay unsupported;
     * their declaration strings are still checked independently. This avoids
     * resolving an earlier statement with a later reassignment's SQL or table.
     *
     * @return array<string, string>
     */
    private static function rawVariables(string $body): array
    {
        $literal = '\'(?:[^\'\\\\]|\\\\.)*\'|"(?:[^"\\\\]|\\\\.)*"';
        preg_match_all('/\$(\w+)\s*=\s*('.$literal.')\s*;/', $body, $assignments, PREG_SET_ORDER);
        $values = [];
        foreach ($assignments as $assignment) {
            $value = self::rawSql($assignment[2], []);

            if ($value !== null) {
                $values[$assignment[1]][] = $value;
            }
        }
        $result = [];
        foreach ($values as $name => $candidates) {
            $unique = array_values(array_unique($candidates));
            $count = preg_match_all('/\$'.preg_quote($name, '/').'\s*=(?!=)/', $body);

            if ($count !== count($candidates)) {
                continue;
            }
            $indexOnly = array_all($unique, static fn (string $sql): bool => preg_match('/^CREATE\s+(?:UNIQUE\s+)?INDEX\b/i', $sql) === 1 && ! str_contains($sql, ';'));
            $identifier = count($unique) === 1 && preg_match('/^[a-z0-9_]+$/', $unique[0]) === 1;
            preg_match_all('/\$'.preg_quote($name, '/').'\s*\.=\s*([^;]+);/s', $body, $appends);

            if ($identifier && $appends[1] === []) {
                $result['$'.$name] = $unique[0];

                continue;
            }

            if (! $indexOnly) {
                continue;
            }
            foreach ($appends[1] as $append) {
                $tails = [self::rawSql($append, [])];

                if (preg_match('/\?\s*('.$literal.')\s*:\s*('.$literal.')\s*$/s', $append, $branches) === 1) {
                    $tails = [self::rawSql($branches[1], []), self::rawSql($branches[2], [])];
                }

                if (array_any($tails, static fn (?string $tail): bool => $tail === null || str_contains($tail, ';') || preg_match('/\b(?:CREATE|ALTER)\b/i', $tail) === 1)) {
                    $indexOnly = false;
                }
            }

            if ($indexOnly) {
                $result['$'.$name] = implode(';', $unique);
            }
        }

        return $result;
    }

    /** @param array<string, string> $variables */
    private static function rawSql(string $argument, array $variables): ?string
    {
        $tokens = array_values(array_filter(array_slice(token_get_all('<?php '.$argument), 1),
            static fn (array|string $token): bool => ! is_array($token) || ! in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)));

        // Existing constraint/trigger templates do not define column types; the
        // grammar still rejects unresolved placeholders in column definitions.
        if (is_array($tokens[0] ?? null) && $tokens[0][0] === T_STRING && strtolower($tokens[0][1]) === 'sprintf'
            && ($tokens[1] ?? null) === '(' && is_array($tokens[2] ?? null) && $tokens[2][0] === T_CONSTANT_ENCAPSED_STRING) {
            $tokens = [$tokens[2]];
        }
        $sql = '';
        foreach ($tokens as $token) {
            if (is_array($token)) {
                if ($token[0] === T_CONSTANT_ENCAPSED_STRING) {
                    $value = substr($token[1], 1, -1);
                    $sql .= $token[1][0] === '"' ? stripcslashes($value) : str_replace(["\\'", '\\\\'], ["'", '\\'], $value);
                } elseif ($token[0] === T_ENCAPSED_AND_WHITESPACE) {
                    $sql .= stripcslashes($token[1]);
                } elseif ($token[0] === T_VARIABLE) {
                    $sql .= $variables[$token[1]] ?? '?';
                } elseif (! in_array($token[0], [T_CURLY_OPEN, T_START_HEREDOC, T_END_HEREDOC], true)) {
                    return null;
                }
            } elseif (! in_array($token, ['"', '}', '.'], true)) {
                return null;
            }
        }

        return str_starts_with(ltrim($sql), '?') ? null : $sql;
    }

    /**
     * Bounded column DDL grammar: CREATE TABLE (...), ALTER TABLE ADD [COLUMN]
     * and ALTER [COLUMN] ... [SET DATA] TYPE. Unknown types/forms fail closed:
     * a domain, LIKE or AS SELECT could otherwise conceal an integer column.
     */
    private function rawIntegers(string $file, int $line, string $sql): void
    {
        if (preg_match('/\b(?:CREATE|ALTER|DO|CALL|EXECUTE)\b/i', $sql) !== 1) {
            return;
        }
        // PHP double-quoted SQL literals escape identifier quotes.
        $sql = str_replace('\\"', '"', $sql);
        preg_match_all('/\'(?:[^\']|\'\')*\'|"(?:[^"]|"")*"|`(?:[^`]|``)*`|--[^\n]*|\/\*.*?\*\/|[a-z_][a-z0-9_$]*|[0-9]+|[^\s]/is', $sql, $matches);
        $tokens = array_values(array_filter($matches[0], static fn (string $token): bool => ! str_starts_with($token, '--') && ! str_starts_with($token, '/*')));

        try {
            foreach (self::sqlParts($tokens, ';') as $statement) {
                // A bare keyword can also be an ordinary Blueprint column name.
                if (count($statement) === 1) {
                    continue;
                }
                $head = strtoupper(implode(' ', array_slice($statement, 0, 4)));

                if (in_array(strtoupper($statement[0]), ['DO', 'CALL', 'EXECUTE'], true)) {
                    throw new RuntimeException('unsupported procedural or dynamic SQL wrapper');
                }

                if (strtoupper($statement[0]) === 'ALTER' && strtoupper($statement[1] ?? '') !== 'TABLE') {
                    throw new RuntimeException('unsupported or computed ALTER target');
                }

                if (! str_starts_with($head, 'CREATE ') && ! ($head === 'ALTER TABLE' || str_starts_with($head, 'ALTER TABLE '))) {
                    if (preg_match('/\b(?:CREATE\s+(?:(?:TEMP(?:ORARY)?|UNLOGGED)\s+)?TABLE|ALTER\s+TABLE)\b/i', implode(' ', $statement)) === 1) {
                        throw new RuntimeException('column DDL inside unsupported SQL wrapper');
                    }

                    continue;
                }
                $create = strtoupper($statement[0]) === 'CREATE';
                $i = $create ? 1 : 2;

                if ($create) {
                    if (in_array(strtoupper($statement[$i] ?? ''), ['TEMP', 'TEMPORARY', 'UNLOGGED'], true)) {
                        $i++;
                    }

                    if (strtoupper($statement[$i] ?? '') !== 'TABLE') {
                        $kind = strtoupper($statement[$i] ?? '');

                        if (! in_array($kind, ['INDEX', 'EXTENSION', 'TRIGGER'], true)
                            && ! ($kind === 'UNIQUE' && strtoupper($statement[$i + 1] ?? '') === 'INDEX')) {
                            throw new RuntimeException('unsupported CREATE form: '.$kind);
                        }

                        if (preg_match('/\b(?:CREATE\s+TABLE|ALTER\s+TABLE)\b/i', implode(' ', array_slice($statement, $i))) === 1) {
                            throw new RuntimeException('nested column DDL inside unsupported CREATE form');
                        }

                        continue;
                    }
                    $i++;

                    if (strtoupper($statement[$i] ?? '') === 'IF') {
                        if (strtoupper(implode(' ', array_slice($statement, $i, 3))) !== 'IF NOT EXISTS') {
                            throw new RuntimeException('unsupported CREATE TABLE guard');
                        }
                        $i += 3;
                    }
                } elseif (strtoupper($statement[$i] ?? '') === 'ONLY') {
                    $i++;
                }
                $table = $statement[$i++] ?? '';

                if (($statement[$i] ?? '') === '.') {
                    if (self::sqlIdentifier($table) !== 'public') {
                        throw new RuntimeException('unsupported schema-qualified table');
                    }
                    $i++;
                    $table = $statement[$i++] ?? '';
                }

                if ($create) {
                    if (($statement[$i++] ?? '') !== '(') {
                        throw new RuntimeException('CREATE TABLE requires explicit column definitions');
                    }
                    $body = array_slice($statement, $i);

                    if (array_pop($body) !== ')') {
                        throw new RuntimeException('unsupported CREATE TABLE suffix or incomplete definition');
                    }
                    foreach (self::sqlParts($body, ',') as $definition) {
                        if (in_array(strtoupper($definition[0] ?? ''), ['CONSTRAINT', 'PRIMARY', 'UNIQUE', 'FOREIGN', 'CHECK', 'EXCLUDE'], true)) {
                            continue;
                        }
                        $this->rawColumn($file, $line, $table, $definition);
                    }

                    continue;
                }

                if ($i >= count($statement)) {
                    throw new RuntimeException('ALTER TABLE requires explicit actions');
                }
                foreach (self::sqlParts(array_slice($statement, $i), ',') as $action) {
                    $verb = strtoupper(array_shift($action) ?? '');

                    if ($verb === 'ADD') {
                        if (in_array(strtoupper($action[0] ?? ''), ['CONSTRAINT', 'PRIMARY', 'UNIQUE', 'FOREIGN', 'CHECK', 'EXCLUDE'], true)) {
                            continue;
                        }

                        if (strtoupper($action[0] ?? '') === 'COLUMN') {
                            array_shift($action);
                        }

                        if (strtoupper($action[0] ?? '') === 'IF') {
                            if (strtoupper(implode(' ', array_slice($action, 0, 3))) !== 'IF NOT EXISTS') {
                                throw new RuntimeException('unsupported ADD COLUMN guard');
                            }
                            $action = array_slice($action, 3);
                        }
                        $this->rawColumn($file, $line, $table, $action);
                    } elseif ($verb === 'ALTER') {
                        if (strtoupper($action[0] ?? '') === 'COLUMN') {
                            array_shift($action);
                        }
                        $column = array_shift($action) ?? '';

                        if (strtoupper(implode(' ', array_slice($action, 0, 3))) === 'SET DATA TYPE') {
                            $action = array_slice($action, 2);
                        }

                        if (strtoupper($action[0] ?? '') === 'TYPE') {
                            $this->rawColumn($file, $line, $table, [$column, ...array_slice($action, 1)]);
                        } elseif (! in_array(strtoupper(implode(' ', array_slice($action, 0, 2))), ['SET DEFAULT', 'DROP DEFAULT', 'SET NOT', 'DROP NOT'], true)) {
                            throw new RuntimeException('unsupported ALTER COLUMN action');
                        }
                    } elseif (! in_array($verb, ['DROP', 'RENAME', 'VALIDATE'], true)) {
                        throw new RuntimeException('unsupported ALTER TABLE action: '.$verb);
                    }
                }
            }
        } catch (RuntimeException $error) {
            $this->hit('S6', $file, $line, 'cannot verify raw column DDL: '.$error->getMessage());
        }
    }

    /** @param list<string> $definition */
    private function rawColumn(string $file, int $line, string $table, array $definition): void
    {
        $table = self::sqlIdentifier($table);
        $column = self::sqlIdentifier($definition[0] ?? '');
        $type = strtolower($definition[1] ?? '');

        // Qualified/domain/quoted types need resolution and are deliberately unsupported.
        if (($definition[2] ?? '') === '.' || ! in_array($type, [
            'smallint', 'int2', 'integer', 'int', 'int4', 'tinyint', 'mediumint', 'serial', 'serial2', 'serial4', 'smallserial',
            'bigint', 'int8', 'bigserial', 'serial8', 'uuid', 'text', 'varchar', 'character', 'char', 'boolean', 'bool',
            'numeric', 'decimal', 'real', 'double', 'float', 'date', 'time', 'timestamp', 'timestamptz', 'timetz', 'interval',
            'json', 'jsonb', 'bytea', 'ltree',
        ], true)) {
            throw new RuntimeException('unsupported column type: '.$table.'.'.$column.' '.($type === '' ? '?' : $type));
        }

        if (in_array($type, ['smallint', 'int2', 'integer', 'int', 'int4', 'tinyint', 'mediumint', 'serial', 'serial2', 'serial4', 'smallserial'], true)
            && ! isset($this->allowlist[$table.'.'.$column])) {
            $this->hit('S6', $file, $line, '32-bit integer outside allowlist: '.$table.'.'.$column);
        }
    }

    private static function sqlIdentifier(string $token): string
    {
        if (preg_match('/^[a-z_][a-z0-9_]*$/i', $token) === 1) {
            return strtolower($token);
        }

        if (preg_match('/^(["`])([a-z_][a-z0-9_]*)\1$/i', $token, $match) === 1) {
            return $match[2];
        }

        throw new RuntimeException('unsupported or missing identifier: '.$token);
    }

    /** @param list<string> $tokens
     * @return list<list<string>> */
    private static function sqlParts(array $tokens, string $delimiter): array
    {
        $parts = [];
        $part = [];
        $depth = 0;
        foreach ($tokens as $token) {
            if ($token === '(' || $token === '[') {
                $depth++;
            } elseif ($token === ')' || $token === ']') {
                $depth--;
            }

            if ($depth < 0) {
                throw new RuntimeException('unbalanced column definition');
            }

            if ($token === $delimiter && $depth === 0) {
                if ($part === []) {
                    throw new RuntimeException('empty column definition');
                }
                $parts[] = $part;
                $part = [];
            } else {
                $part[] = $token;
            }
        }

        if ($depth !== 0) {
            throw new RuntimeException('incomplete column definition');
        }

        if ($part !== []) {
            $parts[] = $part;
        } elseif ($delimiter === ',' && $tokens !== []) {
            throw new RuntimeException('empty trailing column definition');
        }

        return $parts;
    }

    private function purity(string $file, string $source, string $body, int $line): void
    {
        foreach (self::calls($body) as $call) {
            if ((! $call['method'] && in_array($call['name'], ['config', 'env', 'app', 'resolve'], true))
                || ($call['method'] && in_array($call['name'], ['connection', 'insert', 'insertOrIgnore', 'insertGetId', 'update', 'updateOrInsert', 'upsert', 'delete'], true))) {
                $this->hit('S7', $file, $line + $call['line'] - 1, 'impure up() call: '.$call['name']);
            }

            if ($call['method'] && in_array($call['name'], ['enum', 'set'], true)) {
                $this->hit('S5', $file, $line + $call['line'] - 1, 'database enum/set is forbidden');
            }
        }
        $masked = self::withoutStrings($body);
        preg_match_all('/\buse\s+([\w\\\\]+)(?:\s+as\s+(\w+))?\s*;/', $source, $imports, PREG_SET_ORDER);
        foreach ($imports as $import) {
            $class = $import[1];

            if (str_starts_with($class, 'Illuminate\\') || in_array($class, ['RuntimeException', 'LogicException', 'InvalidArgumentException', 'Throwable', 'Exception', 'DateTimeImmutable', 'DateTime', 'DateInterval', 'stdClass'], true)) {
                continue;
            }
            $alias = $import[2] ?? substr($class, (int) strrpos($class, '\\') + 1);

            if (preg_match('/\b'.preg_quote($alias, '/').'\b/', $masked) === 1) {
                $this->hit('S7', $file, $line, 'non-Illuminate class in up(): '.$class);
            }
        }
        foreach (token_get_all('<?php '.$body) as $token) {
            if (! is_array($token)) {
                continue;
            }

            if (in_array($token[0], [T_NAME_FULLY_QUALIFIED, T_NAME_QUALIFIED], true) && ! str_starts_with(ltrim($token[1], '\\'), 'Illuminate\\')) {
                $this->hit('S7', $file, $line + $token[2] - 1, 'non-Illuminate class in up(): '.$token[1]);
            }

            if (in_array($token[0], [T_CONSTANT_ENCAPSED_STRING, T_ENCAPSED_AND_WHITESPACE], true)) {
                $sql = trim($token[1], "\"'");

                if (preg_match('/\bAS\s+ENUM\b/i', $sql) === 1) {
                    $this->hit('S5', $file, $line + $token[2] - 1, 'SQL enum is forbidden');
                }

                if (preg_match('/(?:^|;)\s*(?:INSERT\s+INTO|UPDATE\s+[a-z_][a-z0-9_]*|DELETE\s+FROM)\b/i', $sql) === 1) {
                    $this->hit('S7', $file, $line + $token[2] - 1, 'data-changing SQL in up()');
                }
            }
        }
    }

    private function ownership(string $file, int $line, string $table, string $package, string $scope): void
    {
        $row = $this->catalogue[$table] ?? null;
        $expectedScope = match ($scope) {
            'central' => 'root', 'dedicated' => 'shared-budget', default => 'tenant',
        };

        if ($row === null || $row['owner'] !== $package || $row['scope'] !== $expectedScope) {
            $this->hit('S9', $file, $line, 'table '.$table.' must belong to '.$package.' with scope '.$expectedScope);
        }
    }

    private function object(string $file, int $line, ?string $name, string $table, string $scope, bool $register = true): void
    {
        if ($name === null || strlen($name) > 63 || preg_match('/^[a-z0-9_]+_(pk|uq|ix|fk|ck)$/', $name) !== 1
            || ($table !== '' && ! str_starts_with($name, $table.'_'))) {
            $this->hit('S4', $file, $line, 'invalid literal object name: '.($name ?? '<dynamic>'));

            return;
        }

        if (! $register) {
            return;
        }
        $key = $scope.'/'.$name;

        if (isset($this->objects[$key])) {
            $this->hit('S4', $file, $line, 'duplicate object name: '.$name.' ('.$this->objects[$key].')');
        }
        $this->objects[$key] = $file;
    }

    private function hit(string $code, string $file, int $line, string $message): void
    {
        $this->hits[] = compact('code', 'file', 'line', 'message');
    }

    /** @param SchemaCall $call */
    private static function argument(array $call, int $position, string $parameter): ?string
    {
        foreach ($call['args'] as $argument) {
            if (preg_match('/^'.preg_quote($parameter, '/').'\s*:\s*(.+)$/s', $argument, $match) === 1) {
                return $match[1];
            }
        }

        return $call['args'][$position] ?? null;
    }

    private static function literal(string $value): ?string
    {
        return preg_match('/^([\'"])([a-z0-9_]+)\1$/', trim($value), $match) === 1 ? $match[2] : null;
    }

    private static function code(string $source): string
    {
        $code = '';
        foreach (token_get_all($source, TOKEN_PARSE) as $token) {
            $code .= is_array($token) ? (in_array($token[0], [T_COMMENT, T_DOC_COMMENT], true)
                ? preg_replace('/[^\n]/', ' ', $token[1]) : $token[1]) : $token;
        }

        return $code;
    }

    private static function withoutStrings(string $source): string
    {
        $result = '';
        foreach (token_get_all('<?php '.$source) as $token) {
            $result .= is_array($token) ? (in_array($token[0], [T_CONSTANT_ENCAPSED_STRING, T_ENCAPSED_AND_WHITESPACE], true) ? ' ' : $token[1]) : $token;
        }

        return $result;
    }

    /** @return array{body: string, line: int}|null */
    private static function up(string $source, string $method = 'up'): ?array
    {
        if (preg_match('/function\s+'.preg_quote($method, '/').'\s*\([^)]*\)[^{]*\{/', self::withoutStrings($source), $match) !== 1) {
            return null;
        }
        // Locate the real function token: string masking changes offsets.
        $tokens = token_get_all($source);
        $offset = 0;
        $active = false;
        $depth = 0;
        $start = 0;
        foreach ($tokens as $i => $token) {
            $text = is_array($token) ? $token[1] : $token;

            if (! $active && is_array($token) && $token[0] === T_FUNCTION) {
                $tail = '';
                for ($j = $i + 1; $j < count($tokens) && strlen($tail) < 60; $j++) {
                    $tail .= is_array($tokens[$j]) ? $tokens[$j][1] : $tokens[$j];
                }
                $active = preg_match('/^\s*'.preg_quote($method, '/').'\s*\(/', $tail) === 1;
            }

            if ($active && $token === '{') {
                if ($depth === 0) {
                    $start = $offset + 1;
                }
                $depth++;
            } elseif ($active && is_array($token) && in_array($token[0], [T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES], true)) {
                $depth++;
            } elseif ($active && $token === '}' && --$depth === 0) {
                return ['body' => substr($source, $start, $offset - $start), 'line' => substr_count(substr($source, 0, $start), "\n") + 1];
            }
            $offset += strlen($text);
        }

        return null;
    }

    /** @return list<SchemaCall> */
    private static function calls(string $body): array
    {
        $tokens = token_get_all('<?php '.$body);
        $positions = [];
        $offset = -6;
        foreach ($tokens as $token) {
            $positions[] = $offset;
            $offset += strlen(is_array($token) ? $token[1] : $token);
        }
        $calls = [];
        foreach ($tokens as $i => $token) {
            if (! is_array($token) || ! in_array($token[0], [T_STRING, T_NAME_FULLY_QUALIFIED], true)) {
                continue;
            }
            $j = $i + 1;
            while (isset($tokens[$j]) && is_array($tokens[$j]) && $tokens[$j][0] === T_WHITESPACE) {
                $j++;
            }

            if (($tokens[$j] ?? null) !== '(') {
                continue;
            }
            $depth = 0;
            $args = [];
            $argument = '';
            $end = $j;
            for ($k = $j + 1; $k < count($tokens); $k++) {
                $part = $tokens[$k];

                if (is_array($part)) {
                    $argument .= $part[1];

                    if (in_array($part[0], [T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES], true)) {
                        $depth++;
                    }

                    continue;
                }

                if ($part === ')' && $depth === 0) {
                    if (trim($argument) !== '') {
                        $args[] = trim($argument);
                    }
                    $end = $k;

                    break;
                }

                if (in_array($part, ['(', '[', '{'], true)) {
                    $depth++;
                } elseif (in_array($part, [')', ']', '}'], true)) {
                    $depth--;
                }

                if ($part === ',' && $depth === 0) {
                    $args[] = trim($argument);
                    $argument = '';
                } else {
                    $argument .= $part;
                }
            }
            $prev = $i - 1;
            while ($prev >= 0 && is_array($tokens[$prev]) && $tokens[$prev][0] === T_WHITESPACE) {
                $prev--;
            }
            $method = is_array($tokens[$prev] ?? null) && in_array($tokens[$prev][0], [T_OBJECT_OPERATOR, T_DOUBLE_COLON, T_NULLSAFE_OBJECT_OPERATOR], true);
            $calls[] = ['name' => ltrim($token[1], '\\'), 'args' => $args, 'offset' => $positions[$i], 'end' => $positions[$end], 'line' => $token[2], 'method' => $method];
        }

        return $calls;
    }

    /** @return list<string> */
    private static function files(string $pattern, int $flags = 0): array
    {
        $files = glob($pattern, $flags);

        return $files === false ? [] : $files;
    }

    /** @return list<string> */
    private static function lines(string $path): array
    {
        $lines = file($path);

        return $lines === false ? [] : $lines;
    }

    private static function sets(Illuminate\Foundation\Application $app): SchemaSets
    {
        return $app->make(SchemaSets::class);
    }

    /** @return SchemaManifest */
    public static function manifest(string $root): array
    {
        require_once $root.'/vendor/autoload.php';
        $providers = [OperationsServiceProvider::class];
        foreach (array_merge(self::files($root.'/packages/*/composer.json'), self::files($root.'/products/*/composer.json')) as $composer) {
            /** @var array{extra?: array{laravel?: array{providers?: list<class-string>}}} $data */
            $data = json_decode((string) file_get_contents($composer), true, flags: JSON_THROW_ON_ERROR);
            foreach ($data['extra']['laravel']['providers'] ?? [] as $provider) {
                $providers[] = $provider;
            }
        }
        $resolver = new class extends Application
        {
            protected function defineEnvironment($app): void
            {
                $app['config']->set('database.default', 'schema_golden');
                foreach (['schema_golden', 'schema_budget'] as $name) {
                    $app['config']->set('database.connections.'.$name, ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
                }
                $app['config']->set('corex-operations.schema', [
                    'tenant' => ['connection' => 'schema_golden', 'driver' => 'sqlite', 'database' => ':memory:'],
                    'budget' => ['connection' => 'schema_budget', 'driver' => 'sqlite', 'database' => ':memory:'],
                ]);
            }
        };
        $app = $resolver::create(options: ['extra' => ['providers' => $providers, 'dont-discover' => ['*']]]);
        $codes = ['axiomasoft/corex' => '01', 'axiomasoft/corex-tenancy' => '02', 'axiomasoft/corex-access' => '03',
            'axiomasoft/corex-operations' => '05', 'axiomasoft/corex-webhooks' => '06',
            'axiomasoft/corex-panels' => '07', 'axiomasoft/corex-custom-fields' => '08', 'axiomasoft/corex-intl' => '09',
            'axiomasoft/corex-media' => '10', 'axiomasoft/corex-notifications' => '11', 'axiomasoft/corex-search' => '12',
            'axiomasoft/corex-import-export' => '13', 'axiomasoft/corex-scheduler' => '14', 'axiomasoft/corex-workflow' => '15',
            'axiomasoft/corex-tasks' => '16', 'axiomasoft/corex-recurring-tasks' => '17', 'flex/crm' => '50'];
        $sets = [];

        try {
            foreach (self::sets($app)->all() as $set) {
                // External packages own and validate their migrations. The CoreX gate
                // checks only schemas shipped by this repository, even when a bridge
                // registers an upstream provider in the same application.
                if (str_starts_with($set->path, $root.'/vendor/') && ! isset($codes[$set->package])) {
                    continue;
                }

                if (! isset($codes[$set->package]) || ! str_starts_with($set->path, $root.'/')) {
                    throw new RuntimeException('Unmapped schema package/path: '.$set->id());
                }
                $sets[] = ['id' => $set->id(), 'package' => $set->package, 'name' => $set->name, 'scope' => $set->scope->value,
                    'path' => substr($set->path, strlen($root) + 1), 'version' => $set->version, 'drivers' => $set->drivers,
                    'lifecycle' => $set->lifecycle->value, 'requires' => $set->requires, 'prerequisiteSets' => $set->prerequisiteSets, 'connection' => $set->connection];
            }
        } finally {
            $app->flush();
        }

        return ['packages' => $codes, 'sets' => $sets];
    }

    /** @param list<string> $arguments */
    public static function main(array $arguments): int
    {
        try {
            $root = dirname(__DIR__);

            if (array_diff($arguments, ['--check', '--write-sets']) !== [] || count($arguments) > 1) {
                throw new RuntimeException('Usage: schema-gate.php [--check|--write-sets]');
            }
            $path = $root.'/scripts/schema-sets.json';

            if ($arguments === ['--write-sets']) {
                file_put_contents($path, json_encode(self::manifest($root), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n");
                fwrite(STDOUT, "schema-gate: schema-sets.json written\n");

                return 0;
            }
            /** @var SchemaManifest $manifest */
            $manifest = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);

            if ($arguments === ['--check']) {
                if ($manifest !== self::manifest($root)) {
                    fwrite(STDERR, "schema-gate: registry differs from schema-sets.json; regenerate with --write-sets\n");

                    return 1;
                }
                fwrite(STDOUT, "schema-gate: registry manifest OK\n");

                return 0;
            }
            /** @var array<string, string> $allowlist */
            $allowlist = json_decode((string) file_get_contents($root.'/scripts/schema-gate-allowlist.json'), true, flags: JSON_THROW_ON_ERROR);
            $hits = new SchemaGate($root, $manifest, $allowlist)->run();
            foreach ($hits as $hit) {
                fwrite(STDERR, "{$hit['code']} {$hit['file']}:{$hit['line']} {$hit['message']}\n");
            }

            if ($hits !== []) {
                return 1;
            }
            fwrite(STDOUT, 'schema-gate: OK ('.count($manifest['sets'])." sets)\n");

            return 0;
        } catch (Throwable $error) {
            fwrite(STDERR, 'schema-gate: '.$error->getMessage()."\n");

            return 2;
        }
    }
}

if (debug_backtrace() === []) {
    require_once __DIR__.'/support/cli-arguments.php';

    exit(SchemaGate::main(array_slice(\CoreX\Scripts\cliArguments(), 1)));
}
