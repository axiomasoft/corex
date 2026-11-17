<?php

declare(strict_types=1);

namespace CoreX\Tests\Golden;

use CoreX\Tests\Golden\Support\SchemaCatalogue;
use CoreX\Tests\Golden\Support\SchemaEnvironment;
use Flex\Crm\Tests\Support\AccountFixture;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Packman\Schema\Data\Migration\SchemaSet;
use Packman\Schema\Enums\SchemaScope;
use Throwable;

final class SchemaSqliteGoldenTest extends GoldenTestCase
{
    use SchemaEnvironment;

    public function test_sqlite_tenant_schemas(): void
    {
        $sets = $this->schemaRegistry();
        $connection = DB::connection('schema_golden');
        $this->freshSchema($connection);
        // Physical tenancy is PG-only; SQLite capability sets use the existing host account fixture.
        DB::setDefaultConnection('schema_golden');
        AccountFixture::migrate();
        $selection = $this->schemaSelection($sets, SchemaScope::Tenant, 'sqlite');
        $ids = array_map(static fn (SchemaSet $set): string => $set->id(), $selection);
        // status() preflights the whole scope; project only the supported driver lane.
        $this->useSchemaSelection($selection);
        self::assertNotEmpty($ids);
        $migrator = $this->schemaMigrator();
        self::assertNotEmpty($migrator->migrateSets($ids, 'schema_golden'));
        self::assertSame([], $migrator->migrateSets($ids, 'schema_golden'));
        foreach ($migrator->status(SchemaScope::Tenant, 'schema_golden') as $status) {
            if (in_array($status->set->id(), $ids, true)) {
                self::assertSame([], $status->pending);
                self::assertSame([], $status->unknown);
            }
        }
        $catalogue = SchemaCatalogue::of($connection);
        $this->assertSchemaNames($catalogue);
        $this->assertSchemaGolden($catalogue, __DIR__.'/schema/tenant.sqlite.json');
    }

    public function test_catalogue_keeps_stored_autoindex_identity(): void
    {
        $this->schemaRegistry();
        $connection = DB::connection('schema_golden');
        $this->freshSchema($connection);
        $connection->getSchemaBuilder()->create('schema_name_probe', function (Blueprint $table): void {
            $table->uuid('first');
            $table->uuid('second');
            $table->primary(['first', 'second'], 'schema_name_probe_pk');
        });
        $catalogue = SchemaCatalogue::of($connection);
        self::assertSame('sqlite_autoindex_schema_name_probe_1', $catalogue['tables']['schema_name_probe']['indexes'][0]['name']);
    }

    public function test_new_long_sqlite_names_fail_without_an_existing_pending_exception(): void
    {
        $this->schemaRegistry();
        $connection = DB::connection('schema_golden');
        $this->freshSchema($connection);
        $connection->getSchemaBuilder()->create('schema_name_probe', function (Blueprint $table): void {
            $table->uuid('id');
            $table->index(['id'], 'schema_name_probe_'.str_repeat('x', 64).'_ix');
        });
        $catalogue = SchemaCatalogue::of($connection);
        $failure = null;

        try {
            $this->assertSchemaNames($catalogue);
        } catch (Throwable $exception) {
            $failure = $exception;
        }
        self::assertNotNull($failure, 'An unlisted long SQLite name must fail.');
        self::assertStringContainsString('Schema name: schema_name_probe.', $failure->getMessage());
    }
}
