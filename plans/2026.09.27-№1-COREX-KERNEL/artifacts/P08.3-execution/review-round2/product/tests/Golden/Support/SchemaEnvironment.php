<?php

declare(strict_types=1);

namespace CoreX\Tests\Golden\Support;

use CoreX\Database\Contracts\SchemaMigrator;
use CoreX\Database\Contracts\SchemaSets;
use CoreX\Database\Data\SchemaSet;
use CoreX\Database\Enums\SchemaScope;
use CoreX\Database\Internal\TaggedSchemaSets;
use CoreX\Operations\Laravel\ApiSchemaRegistrar;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Shared registry and disposable connections for schema snapshots and the static manifest.
 *
 * @phpstan-import-type Catalogue from SchemaCatalogue
 */
trait SchemaEnvironment
{
    protected function defineDatabaseMigrations(): void {}

    public function schemaRegistry(string $driver = 'sqlite'): SchemaSets
    {
        $config = $driver === 'pgsql' ? config('database.connections.pgsql') : config('database.connections.testbench');

        if (! is_array($config)) {
            throw new RuntimeException('Missing schema test connection.');
        }
        config(['database.connections.schema_golden' => $config, 'database.connections.schema_budget' => $config]);
        DB::purge('schema_golden');
        DB::purge('schema_budget');
        config(['corex-operations.schema' => [
            'tenant' => ['connection' => 'schema_golden', 'driver' => $driver, 'database' => $config['database']],
            'budget' => ['connection' => 'schema_budget', 'driver' => $driver, 'database' => $config['database']],
        ]]);
        app()->forgetInstance(ApiSchemaRegistrar::class);
        app()->forgetInstance(SchemaSets::class);
        app()->forgetInstance(SchemaMigrator::class);

        return app(SchemaSets::class);
    }

    public function freshSchema(Connection $connection): void
    {
        if (! app()->environment('testing')) {
            throw new RuntimeException('Schema snapshots require APP_ENV=testing.');
        }

        if ($connection->getDriverName() === 'sqlite') {
            if ($connection->getDatabaseName() !== ':memory:') {
                throw new RuntimeException('SQLite schema snapshots require :memory:.');
            }

            return;
        }

        if ($connection->getDriverName() !== 'pgsql' || preg_match('/^[a-z0-9_]+_test$/', $connection->getDatabaseName()) !== 1) {
            throw new RuntimeException('PostgreSQL schema snapshots require PGSQL_DATABASE ending in _test.');
        }
        /** @var object{name: string}|null $actual */
        $actual = $connection->selectOne('SELECT current_database() AS name');

        if ($actual === null || $actual->name !== $connection->getDatabaseName()) {
            throw new RuntimeException('Schema snapshot connection does not match the configured test database.');
        }
        $connection->statement('DROP SCHEMA IF EXISTS public CASCADE');
        $connection->statement('CREATE SCHEMA public');
    }

    /** @return list<SchemaSet> */
    public function schemaSelection(SchemaSets $sets, SchemaScope $scope, string $driver): array
    {
        return array_values(array_filter($sets->forScope($scope, includeModules: true),
            static fn (SchemaSet $set): bool => in_array($driver, $set->drivers, true)));
    }

    /** @param list<SchemaSet> $selection */
    public function useSchemaSelection(array $selection): void
    {
        $provider = new SchemaSelectionProvider($selection);
        app()->instance(SchemaSets::class, new TaggedSchemaSets([$provider]));
        app()->forgetInstance(SchemaMigrator::class);
    }

    public function schemaMigrator(): SchemaMigrator
    {
        return app(SchemaMigrator::class);
    }

    /** @param Catalogue $catalogue */
    public function assertSchemaNames(array $catalogue): void
    {
        /** @var array<string, array{package: string, reason: string}> $exceptions */
        $exceptions = $catalogue['driver'] === 'sqlite' && is_file(dirname(__DIR__, 3).'/scripts/schema-golden-name-pending.json') ? json_decode((string) file_get_contents(dirname(__DIR__, 3).'/scripts/schema-golden-name-pending.json'), true, flags: JSON_THROW_ON_ERROR) : [];
        /** @var list<string> $pending */
        $pending = is_file(dirname(__DIR__, 3).'/scripts/schema-gate-pending.json') ? json_decode((string) file_get_contents(dirname(__DIR__, 3).'/scripts/schema-gate-pending.json'), true, flags: JSON_THROW_ON_ERROR) : [];
        $observed = [];
        foreach ($catalogue['tables'] as $table => $definition) {
            self::assertLessThanOrEqual(63, strlen($table));
            foreach (array_keys($definition['columns']) as $column) {
                self::assertLessThanOrEqual(63, strlen($column));
            }
            foreach (['indexes', 'foreign_keys', 'checks'] as $kind) {
                foreach ($definition[$kind] as $object) {
                    $key = $table.'.'.$object['name'];

                    if (strlen($object['name']) > 63 && isset($exceptions[$key])) {
                        self::assertContains($exceptions[$key]['package'], $pending, 'Expired SQLite name exception: '.$key);
                        $observed[] = $key;
                    } else {
                        self::assertLessThanOrEqual(63, strlen($object['name']), 'Schema name: '.$key);
                    }
                }
            }
        }
        sort($observed);
        $expected = array_keys($exceptions);
        sort($expected);
        self::assertSame($expected, $observed, 'Remove stale SQLite name exceptions as packages leave the pending baseline.');
    }

    /** @param Catalogue $catalogue */
    public function assertSchemaGolden(array $catalogue, string $path): void
    {
        if (getenv('GOLDEN_UPDATE') === '1') {
            file_put_contents($path, SchemaCatalogue::encode($catalogue));
        }

        if (! is_file($path)) {
            throw new RuntimeException("Missing schema golden {$path}; initialize intentionally with GOLDEN_UPDATE=1.");
        }
        /** @var Catalogue $expected */
        $expected = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
        // Per-table assertions keep a schema drift diagnostic small.
        self::assertSame(array_keys($expected['tables']), array_keys($catalogue['tables']));
        foreach ($expected['tables'] as $name => $table) {
            self::assertSame($table, $catalogue['tables'][$name], 'Schema drift: '.$name);
        }
        self::assertSame($expected['enum_types'], $catalogue['enum_types']);
        self::assertSame($expected['driver'], $catalogue['driver']);
    }
}
