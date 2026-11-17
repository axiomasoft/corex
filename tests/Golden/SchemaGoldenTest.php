<?php

declare(strict_types=1);

namespace CoreX\Tests\Golden;

use CoreX\Tests\Golden\Support\SchemaCatalogue;
use CoreX\Tests\Golden\Support\SchemaEnvironment;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\PendingCommand;
use Packman\Schema\Enums\SchemaScope;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

final class SchemaGoldenTest extends GoldenTestCase
{
    use SchemaEnvironment;

    #[Group('pg')]
    #[DataProvider('scopes')]
    public function test_fresh_postgres_schemas(SchemaScope $scope): void
    {
        $this->schemaRegistry('pgsql');
        $name = $scope === SchemaScope::Dedicated ? 'schema_budget' : 'schema_golden';
        $connection = DB::connection($name);
        $this->freshSchema($connection);
        $migrator = $this->schemaMigrator();
        $override = $scope === SchemaScope::Dedicated ? null : $name;

        // Pending historical packages reference the core/tenancy baselines before their own squash.
        if ($scope === SchemaScope::Tenant) {
            $migrator->migrateSets(['axiomasoft/corex:tenant'], $name);
            $migrator->migrateSets(['axiomasoft/corex-tenancy:tenant'], $name);
        }
        self::assertNotEmpty($migrator->migrate($scope, $override, includeModules: true));
        self::assertSame([], $migrator->migrate($scope, $override, includeModules: true));
        foreach ($migrator->status($scope, $override) as $status) {
            self::assertSame([], $status->pending);
            self::assertSame([], $status->unknown);
        }
        $catalogue = SchemaCatalogue::of($connection);
        self::assertSame([], $catalogue['enum_types']);
        $this->assertSchemaNames($catalogue);
        $command = $this->artisan('packman:schema:migrate', ['--scope' => $scope->value, '--modules' => true, ...($override === null ? [] : ['--database' => $override])]);
        self::assertInstanceOf(PendingCommand::class, $command);
        $command->expectsOutput('Nothing to migrate.')->assertSuccessful();
        $this->assertSchemaGolden($catalogue, __DIR__.'/schema/'.$scope->value.'.pgsql.json');
    }

    #[Group('pg')]
    #[DataProvider('uniqueForms')]
    public function test_catalogue_distinguishes_null_uniqueness_with_identical_name_and_columns(bool $constraint): void
    {
        $this->schemaRegistry('pgsql');
        $connection = DB::connection('schema_golden');
        $this->freshSchema($connection);
        $connection->statement('CREATE TABLE schema_null_probe (workspace_id uuid, code text)');
        $create = $constraint
            ? 'ALTER TABLE schema_null_probe ADD CONSTRAINT schema_null_probe_uq UNIQUE NULLS NOT DISTINCT (workspace_id, code)'
            : 'CREATE UNIQUE INDEX schema_null_probe_uq ON schema_null_probe (workspace_id, code) NULLS NOT DISTINCT';
        $connection->statement($create);
        $before = SchemaCatalogue::of($connection);
        $index = $before['tables']['schema_null_probe']['indexes'][0];
        self::assertSame('schema_null_probe_uq', $index['name']);
        self::assertSame('uq', $index['kind']);
        self::assertSame(['workspace_id', 'code'], $index['columns']);
        self::assertSame(true, $index['nulls_not_distinct'] ?? null);
        $connection->statement($constraint
            ? 'ALTER TABLE schema_null_probe DROP CONSTRAINT schema_null_probe_uq'
            : 'DROP INDEX schema_null_probe_uq');
        $connection->statement(str_replace(' NULLS NOT DISTINCT', '', $create));
        $after = SchemaCatalogue::of($connection);
        self::assertSame(false, $after['tables']['schema_null_probe']['indexes'][0]['nulls_not_distinct'] ?? null);
        self::assertNotSame(SchemaCatalogue::encode($before), SchemaCatalogue::encode($after));
        // No incidental name/column/other semantic change may explain the delta.
        $before['tables']['schema_null_probe']['indexes'][0]['nulls_not_distinct'] = false;
        self::assertSame($before, $after);
    }

    /** @return list<array{bool}> */
    public static function uniqueForms(): array
    {
        return [[false], [true]];
    }

    /** @return list<array{SchemaScope}> */
    public static function scopes(): array
    {
        return [[SchemaScope::Tenant], [SchemaScope::Central], [SchemaScope::Dedicated]];
    }
}
