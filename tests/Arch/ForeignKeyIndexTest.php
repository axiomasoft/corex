<?php

declare(strict_types=1);

// PostgreSQL does not index the referencing side of a foreign key. Without an
// index whose leading columns are the key columns, every delete or key update
// on the parent table scans the child table to enforce the constraint, and
// joins from the parent fall back to sequential scans. The schema goldens are
// proven equal to the migrated schema, so checking them covers every scope.

// Tables created by vendored Packman migrations, outside this repository.
const FOREIGN_KEY_INDEX_VENDOR_TABLES = ['sys_saved_views'];

/** @return list<string> */
function foreignKeysWithoutLeadingIndex(string $golden): array
{
    /** @var array{tables: array<string, array{indexes: list<array{columns: list<string>, predicate: ?string, method: string}>, foreign_keys: list<array{name: string, columns: list<string>}>}>} $schema */
    $schema = json_decode((string) file_get_contents($golden), true, flags: JSON_THROW_ON_ERROR);
    $offenders = [];

    foreach ($schema['tables'] as $table => $definition) {
        if (in_array($table, FOREIGN_KEY_INDEX_VENDOR_TABLES, true)) {
            continue;
        }

        foreach ($definition['foreign_keys'] as $foreignKey) {
            $columns = $foreignKey['columns'];
            // An equality lookup on every FK column also implies each is non-null.
            // Other partial predicates can hide referencing rows (including soft deletes).
            $safePredicates = array_map(static fn (string $column): string => '('.$column.' IS NOT NULL)', $columns);
            $covered = array_any(
                $definition['indexes'],
                static fn (array $index): bool => $index['method'] === 'btree'
                    && array_slice($index['columns'], 0, count($columns)) === $columns
                    && ($index['predicate'] === null || in_array($index['predicate'], $safePredicates, true)),
            );

            if (! $covered) {
                $offenders[] = $table.'.'.$foreignKey['name'].' ('.implode(', ', $foreignKey['columns']).')';
            }
        }
    }

    return $offenders;
}

it('backs every foreign key with an index on its leading columns', function (string $golden): void {
    expect(foreignKeysWithoutLeadingIndex($golden))->toBe([]);
})->with(glob(dirname(__DIR__).'/Golden/schema/*.pgsql.json') ?: []);
