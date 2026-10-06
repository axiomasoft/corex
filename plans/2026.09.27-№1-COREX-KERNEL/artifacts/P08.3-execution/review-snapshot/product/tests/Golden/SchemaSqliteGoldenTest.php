<?php

declare(strict_types=1);

namespace CoreX\Tests\Golden;

use CoreX\Database\Data\SchemaSet;
use CoreX\Database\Enums\SchemaScope;
use CoreX\Tests\Golden\Support\SchemaCatalogue;
use CoreX\Tests\Golden\Support\SchemaEnvironment;
use Flex\Crm\Tests\Support\AccountFixture;
use Illuminate\Support\Facades\DB;

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
        $this->assertSchemaGolden(SchemaCatalogue::of($connection), __DIR__.'/schema/tenant.sqlite.json');
    }
}
