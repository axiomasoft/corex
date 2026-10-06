<?php

declare(strict_types=1);

namespace CoreX\Tests\Golden;

use CoreX\Database\Enums\SchemaScope;
use CoreX\Tests\Golden\Support\SchemaCatalogue;
use CoreX\Tests\Golden\Support\SchemaEnvironment;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\PendingCommand;
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

        // Pending historical packages reference the core baseline before their own squash.
        if ($scope === SchemaScope::Tenant) {
            $migrator->migrateSets(['axiomasoft/corex:tenant'], $name);
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
        $command = $this->artisan('corex:schema:migrate', ['--scope' => $scope->value, '--modules' => true, ...($override === null ? [] : ['--database' => $override])]);
        self::assertInstanceOf(PendingCommand::class, $command);
        $command->expectsOutput('Nothing to migrate.')->assertSuccessful();
        $this->assertSchemaGolden($catalogue, __DIR__.'/schema/'.$scope->value.'.pgsql.json');
    }

    /** @return list<array{SchemaScope}> */
    public static function scopes(): array
    {
        return [[SchemaScope::Tenant], [SchemaScope::Central], [SchemaScope::Dedicated]];
    }
}
