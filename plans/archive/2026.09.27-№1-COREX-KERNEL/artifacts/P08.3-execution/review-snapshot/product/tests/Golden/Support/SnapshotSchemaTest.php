<?php

declare(strict_types=1);

namespace CoreX\Tests\Golden;

use CoreX\Database\Enums\SchemaScope;
use CoreX\Tests\Golden\Support\SchemaCatalogue;
use CoreX\Tests\Golden\Support\SchemaEnvironment;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;
use RuntimeException;

final class SnapshotSchemaTest extends GoldenTestCase
{
    use SchemaEnvironment;

    #[Group('pg')]
    public function test_opt_in_schema_export(): void
    {
        $selection = getenv('SNAPSHOT_SETS');

        if ($selection === false || $selection === '') {
            self::assertFalse(getenv('SNAPSHOT_SETS') !== false && getenv('SNAPSHOT_SETS') !== '');

            return;
        }
        $out = getenv('SNAPSHOT_OUT');

        if (! is_string($out) || $out === '') {
            throw new RuntimeException('SNAPSHOT_OUT is required when SNAPSHOT_SETS is set.');
        }
        $sets = $this->schemaRegistry('pgsql');
        $ids = explode(',', $selection);
        $name = $sets->get($ids[0])->scope === SchemaScope::Dedicated ? 'schema_budget' : 'schema_golden';
        $connection = DB::connection($name);
        $this->freshSchema($connection);
        $this->schemaMigrator()->migrateSets($ids, $name === 'schema_budget' ? null : $name);
        file_put_contents($out, SchemaCatalogue::encode(SchemaCatalogue::of($connection)));
        self::assertFileExists($out);
    }
}
