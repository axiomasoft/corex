<?php

declare(strict_types=1);

namespace CoreX\Tests\Golden\Support;

use Illuminate\Database\Connection;
use RuntimeException;

/**
 * Logical schema only: no OIDs, ACLs, physical identity or sequence values.
 *
 * @phpstan-type Row array<string, string|int|bool|null>
 * @phpstan-type Column array{type: string, nullable: bool, default: ?string}
 * @phpstan-type Index array{name: string, kind: string, columns: list<string>, predicate: ?string, method: string, nulls_not_distinct?: bool}
 * @phpstan-type ForeignKey array{name: string, columns: list<string>, references: string, on_delete: string, on_update: string, deferrable: bool, initially_deferred: bool}
 * @phpstan-type Check array{name: string, expression: string}
 * @phpstan-type Table array{columns: array<string, Column>, indexes: list<Index>, foreign_keys: list<ForeignKey>, checks: list<Check>, triggers: array<string, string>}
 * @phpstan-type Catalogue array{driver: string, tables: array<string, Table>, enum_types: list<string>}
 */
final class SchemaCatalogue
{
    /** @return Catalogue */
    public static function of(Connection $connection): array
    {
        $driver = $connection->getDriverName();
        $tables = [];

        if ($driver === 'pgsql') {
            $names = self::rows($connection, "SELECT tablename AS name FROM pg_tables WHERE schemaname = 'public' AND tablename <> 'migrations' ORDER BY tablename COLLATE \"C\"");
        } elseif ($driver === 'sqlite') {
            $names = self::rows($connection, "SELECT name, sql FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%' AND name <> 'migrations' ORDER BY name");
        } else {
            throw new RuntimeException('Unsupported schema catalogue driver: '.$driver);
        }
        foreach ($names as $row) {
            $name = (string) $row['name'];
            $tables[$name] = $driver === 'pgsql' ? self::postgres($connection, $name) : self::sqlite($connection, $name, (string) $row['sql']);
        }
        $enums = $driver === 'pgsql' ? array_map(static fn (array $row): string => (string) $row['name'],
            self::rows($connection, "SELECT t.typname AS name FROM pg_type t JOIN pg_namespace n ON n.oid = t.typnamespace WHERE t.typtype = 'e' AND n.nspname = 'public' ORDER BY t.typname COLLATE \"C\"")) : [];

        return ['driver' => $driver, 'tables' => $tables, 'enum_types' => $enums];
    }

    /** @return Table */
    private static function postgres(Connection $connection, string $table): array
    {
        $columns = [];
        foreach (self::rows($connection, <<<'SQL'
            SELECT a.attname AS name, format_type(a.atttypid, a.atttypmod) AS type,
                   NOT a.attnotnull AS nullable, pg_get_expr(d.adbin, d.adrelid, false) AS default_value
            FROM pg_attribute a JOIN pg_class c ON c.oid = a.attrelid
            JOIN pg_namespace n ON n.oid = c.relnamespace
            LEFT JOIN pg_attrdef d ON d.adrelid = a.attrelid AND d.adnum = a.attnum
            WHERE n.nspname = 'public' AND c.relname = ? AND a.attnum > 0 AND NOT a.attisdropped
            ORDER BY a.attname COLLATE "C"
            SQL, [$table]) as $row) {
            $columns[(string) $row['name']] = ['type' => (string) $row['type'], 'nullable' => (bool) $row['nullable'], 'default' => $row['default_value'] === null ? null : (string) $row['default_value']];
        }
        $indexes = [];
        foreach (self::rows($connection, <<<'SQL'
            SELECT x.relname AS name, am.amname AS method, CASE WHEN i.indisprimary THEN 'pk' WHEN i.indisunique THEN 'uq' ELSE 'ix' END AS kind,
                   (SELECT json_agg(pg_get_indexdef(i.indexrelid, k, false) ORDER BY k)
                    FROM generate_series(1, i.indnatts) AS k)::text AS columns,
                   pg_get_expr(i.indpred, i.indrelid, false) AS predicate, i.indnullsnotdistinct AS nulls_not_distinct
            FROM pg_index i JOIN pg_class c ON c.oid = i.indrelid
            JOIN pg_namespace n ON n.oid = c.relnamespace JOIN pg_class x ON x.oid = i.indexrelid
            JOIN pg_am am ON am.oid = x.relam
            WHERE n.nspname = 'public' AND c.relname = ? ORDER BY x.relname COLLATE "C"
            SQL, [$table]) as $row) {
            $indexes[] = ['name' => (string) $row['name'], 'kind' => (string) $row['kind'], 'columns' => self::stringList((string) $row['columns']), 'predicate' => $row['predicate'] === null ? null : (string) $row['predicate'], 'method' => (string) $row['method'], 'nulls_not_distinct' => (bool) $row['nulls_not_distinct']];
        }
        $foreign = [];
        $checks = [];
        foreach (self::rows($connection, <<<'SQL'
            SELECT con.conname AS name, con.contype AS kind, pg_get_expr(con.conbin, con.conrelid, false) AS expression,
                   pg_get_constraintdef(con.oid, false) AS definition, con.condeferrable AS deferrable, con.condeferred AS initially_deferred, con.confdeltype AS on_delete, con.confupdtype AS on_update,
                   (SELECT json_agg(a.attname ORDER BY k.ordinality)
                    FROM unnest(con.conkey) WITH ORDINALITY AS k(num, ordinality)
                    JOIN pg_attribute a ON a.attrelid = con.conrelid AND a.attnum = k.num)::text AS columns
            FROM pg_constraint con JOIN pg_class c ON c.oid = con.conrelid JOIN pg_namespace n ON n.oid = c.relnamespace
            WHERE n.nspname = 'public' AND c.relname = ? AND con.contype IN ('f', 'c')
            ORDER BY con.conname COLLATE "C"
            SQL, [$table]) as $row) {
            if ($row['kind'] === 'c') {
                $checks[] = ['name' => (string) $row['name'], 'expression' => (string) $row['expression']];
            } else {
                preg_match('/REFERENCES (.+?\([^)]*\))/', (string) $row['definition'], $match);
                $foreign[] = ['name' => (string) $row['name'], 'columns' => self::stringList((string) $row['columns']),
                    'references' => str_replace(' ', '', $match[1] ?? throw new RuntimeException('Cannot parse PostgreSQL foreign key.')), 'on_delete' => self::action((string) $row['on_delete']), 'on_update' => self::action((string) $row['on_update']), 'deferrable' => (bool) $row['deferrable'], 'initially_deferred' => (bool) $row['initially_deferred']];
            }
        }
        $triggers = [];
        foreach (self::rows($connection, <<<'SQL'
            SELECT t.tgname AS name, pg_get_triggerdef(t.oid, false) AS definition
            FROM pg_trigger t JOIN pg_class c ON c.oid = t.tgrelid JOIN pg_namespace n ON n.oid = c.relnamespace
            WHERE n.nspname = 'public' AND c.relname = ? AND NOT t.tgisinternal ORDER BY t.tgname COLLATE "C"
            SQL, [$table]) as $row) {
            $triggers[(string) $row['name']] = (string) $row['definition'];
        }

        return ['columns' => $columns, 'indexes' => $indexes, 'foreign_keys' => $foreign, 'checks' => $checks, 'triggers' => $triggers];
    }

    /** @return Table */
    private static function sqlite(Connection $connection, string $table, string $sql): array
    {
        $quoted = '"'.str_replace('"', '""', $table).'"';
        $columns = [];
        $primary = [];
        foreach (self::rows($connection, 'PRAGMA table_xinfo('.$quoted.')') as $row) {
            $name = (string) $row['name'];
            $columns[$name] = ['type' => strtolower((string) $row['type']), 'nullable' => ! (bool) $row['notnull'] && ! (bool) $row['pk'],
                'default' => $row['dflt_value'] === null ? null : (string) $row['dflt_value']];

            if ((int) $row['pk'] > 0) {
                $primary[(int) $row['pk']] = $name;
            }
        }
        ksort($columns, SORT_STRING);
        $indexes = [];
        foreach (self::rows($connection, 'PRAGMA index_list('.$quoted.')') as $index) {
            $name = (string) $index['name'];
            $row = (self::rows($connection, "SELECT sql FROM sqlite_master WHERE type = 'index' AND name = ?", [$name])[0] ?? null);
            $definition = $row === null || $row['sql'] === null ? '' : (string) $row['sql'];
            $parts = [];
            $expressions = self::indexExpressions($definition);
            foreach (self::rows($connection, 'PRAGMA index_xinfo("'.str_replace('"', '""', $name).'")') as $column) {
                if ((int) $column['key'] === 1) {
                    // For expression indexes, retain the SQL expression, not a null PRAGMA name.
                    $parts[] = $column['name'] === null ? ($expressions[(int) $column['seqno']] ?? throw new RuntimeException('Cannot parse SQLite index expression.')) : (string) $column['name'].((int) $column['desc'] === 1 ? ' DESC' : '');
                }
            }
            $kind = $index['origin'] === 'pk' ? 'pk' : ((bool) $index['unique'] ? 'uq' : 'ix');

            if (str_starts_with($name, 'sqlite_autoindex_')) {
                // SQLite discards explicit constraint names in PRAGMA; recover them from DDL.
                preg_match('/constraint\s+["`]?([a-z0-9_]+)["`]?\s+'.($kind === 'pk' ? 'primary\s+key' : 'unique').'\s*\(\s*'.preg_quote('"'.implode('", "', $parts).'"', '/').'\s*\)/i', $sql, $match);
                $name = $match[1] ?? $name; // Keep SQLite's actual autoindex identity when the DDL has no name.
            }
            preg_match('/\bWHERE\s+(.+)$/is', $definition, $predicate);
            $indexes[] = ['name' => $name, 'kind' => $kind, 'columns' => $parts, 'predicate' => $predicate[1] ?? null, 'method' => 'btree'];
        }

        if ($primary !== [] && ! in_array('pk', array_column($indexes, 'kind'), true)) {
            ksort($primary);
            preg_match('/constraint\s+["`]?([a-z0-9_]+)["`]?\s+primary\s+key/i', $sql, $match);
            $indexes[] = ['name' => $match[1] ?? $table.'_pk', 'kind' => 'pk', 'columns' => array_values($primary), 'predicate' => null, 'method' => 'btree'];
        }
        usort($indexes, static fn (array $a, array $b): int => strcmp($a['name'], $b['name']));
        $foreignRows = [];
        foreach (self::rows($connection, 'PRAGMA foreign_key_list('.$quoted.')') as $row) {
            $foreignRows[(int) $row['id']][] = $row;
        }
        $foreign = [];
        foreach ($foreignRows as $rows) {
            usort($rows, static fn (array $a, array $b): int => (int) $a['seq'] <=> (int) $b['seq']);
            $names = array_map(static fn (array $row): string => (string) $row['from'], $rows);
            preg_match('/(?:constraint\s+["`]?([a-z0-9_]+)["`]?\s+)?foreign\s+key\s*\(\s*'.preg_quote('"'.implode('", "', $names).'"', '/').'\s*\)/i', $sql, $match);
            $foreign[] = ['name' => ($match[1] ?? '') !== '' ? $match[1] : $table.'_'.implode('_', $names).'_fk', 'columns' => $names,
                'references' => (string) $rows[0]['table'].'('.implode(',', array_map(static fn (array $row): string => (string) $row['to'], $rows)).')',
                'on_delete' => (string) $rows[0]['on_delete'], 'on_update' => (string) $rows[0]['on_update'], 'deferrable' => false, 'initially_deferred' => false];
        }
        usort($foreign, static fn (array $a, array $b): int => strcmp($a['name'], $b['name']));
        $checks = [];
        // Blueprint enums compile to inline CHECKs on SQLite; unnamed checks remain visible.
        preg_match_all('/(?:constraint\s+["`]?([a-z0-9_]+)["`]?\s+)?check\s*\(/i', $sql, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);
        foreach ($matches as $i => $match) {
            $start = $match[0][1] + strlen($match[0][0]);
            $depth = 1;
            $end = $start;
            while ($end < strlen($sql) && $depth > 0) {
                $depth += $sql[$end] === '(' ? 1 : ($sql[$end] === ')' ? -1 : 0);
                $end++;
            }
            $checks[] = ['name' => ($match[1][0] ?? '') !== '' ? $match[1][0] : $table.'_check_'.$i,
                'expression' => substr($sql, $start, $end - $start - 1)];
        }
        usort($checks, static fn (array $a, array $b): int => strcmp($a['name'], $b['name']));
        $triggers = [];
        foreach (self::rows($connection, "SELECT name, sql FROM sqlite_master WHERE type = 'trigger' AND tbl_name = ? ORDER BY name", [$table]) as $row) {
            $triggers[(string) $row['name']] = (string) $row['sql'];
        }

        return ['columns' => $columns, 'indexes' => $indexes, 'foreign_keys' => $foreign, 'checks' => $checks, 'triggers' => $triggers];
    }

    /** @return list<string> */
    private static function indexExpressions(string $sql): array
    {
        if (preg_match('/\bON\s+(?:"(?:[^"]|"")*"|[a-z0-9_]+)\s*\(/i', $sql, $match, PREG_OFFSET_CAPTURE) !== 1) {
            return [];
        }
        $start = $match[0][1] + strlen($match[0][0]);
        $depth = 1;
        $quote = null;
        $part = '';
        $parts = [];
        for ($i = $start; $i < strlen($sql); $i++) {
            $char = $sql[$i];

            if ($quote !== null) {
                $part .= $char;

                if ($char === $quote) {
                    if (($sql[$i + 1] ?? '') === $quote) {
                        $part .= $sql[++$i];
                    } else {
                        $quote = null;
                    }
                }

                continue;
            }

            if ($char === "'" || $char === '"') {
                $quote = $char;
            } elseif ($char === '(') {
                $depth++;
            } elseif ($char === ')' && --$depth === 0) {
                $parts[] = trim($part);

                return $parts;
            } elseif ($char === ',' && $depth === 1) {
                $parts[] = trim($part);
                $part = '';

                continue;
            }
            $part .= $char;
        }

        throw new RuntimeException('Unclosed SQLite index definition.');
    }

    /** @param list<string> $bindings
     * @return list<Row> */
    private static function rows(Connection $connection, string $sql, array $bindings = []): array
    {
        $result = [];
        foreach ($connection->select($sql, $bindings) as $value) {
            if (! is_object($value)) {
                throw new RuntimeException('Schema query must return records.');
            }
            $row = [];
            foreach (get_object_vars($value) as $key => $cell) {
                if (! is_string($key)) {
                    throw new RuntimeException('Invalid schema query column name.');
                }

                if (! is_string($cell) && ! is_int($cell) && ! is_bool($cell) && $cell !== null) {
                    throw new RuntimeException('Unsupported schema catalogue value.');
                }
                $row[$key] = $cell;
            }
            $result[] = $row;
        }

        return $result;
    }

    private static function action(string $code): string
    {
        return match ($code) {
            'a' => 'NO ACTION', 'r' => 'RESTRICT', 'c' => 'CASCADE', 'n' => 'SET NULL', 'd' => 'SET DEFAULT', default => throw new RuntimeException('Unknown FK action.'),
        };
    }

    /** @return list<string> */
    private static function stringList(string $json): array
    {
        $list = json_decode($json, true, flags: JSON_THROW_ON_ERROR);

        if (! is_array($list) || ! array_is_list($list)) {
            throw new RuntimeException('Invalid catalogue list.');
        }
        foreach ($list as $value) {
            if (! is_string($value)) {
                throw new RuntimeException('Invalid catalogue string.');
            }
        }

        return $list;
    }

    /** @param Catalogue $catalogue */
    public static function encode(array $catalogue): string
    {
        return json_encode($catalogue, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)."\n";
    }
}
