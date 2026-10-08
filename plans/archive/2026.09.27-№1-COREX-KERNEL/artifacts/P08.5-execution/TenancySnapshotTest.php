<?php

declare(strict_types=1);

namespace CoreX\Tests\Golden;

use CoreX\Tests\Golden\Support\SchemaCatalogue;
use CoreX\Tests\Golden\Support\SchemaEnvironment;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;

final class TenancySnapshotTest extends GoldenTestCase
{
    use SchemaEnvironment;

    #[Group('pg')]
    public function test_snapshot_tenant_and_its_core_dependency(): void
    {
        $this->schemaRegistry('pgsql');
        $connection = DB::connection('schema_golden');
        $this->freshSchema($connection);
        // Historical tenancy precedes the core baseline date until it is squashed.
        $this->schemaMigrator()->migrateSets(['axiomasoft/corex:tenant'], 'schema_golden');
        $this->schemaMigrator()->migrateSets(['axiomasoft/corex-tenancy:tenant'], 'schema_golden');
        $out = getenv('SNAPSHOT_OUT');
        self::assertIsString($out);
        file_put_contents($out, SchemaCatalogue::encode(SchemaCatalogue::of($connection)));
        self::assertFileExists($out);
    }
}
