<?php

declare(strict_types=1);

namespace CoreX\Tests\Golden;

use CoreX\Tests\Golden\Support\SchemaCatalogue;
use CoreX\Tests\Golden\Support\SchemaEnvironment;
use Flex\Crm\Tests\Support\AccountFixture;
use Illuminate\Support\Facades\DB;

final class ScopeSnapshotTest extends GoldenTestCase
{
    use SchemaEnvironment;

    public function test_selected_sets_in_dependency_order(): void
    {
        $driver = getenv('SNAPSHOT_DRIVER') ?: 'pgsql';
        $sets = $this->schemaRegistry($driver);
        $ids = explode(',', (string) getenv('SNAPSHOT_SETS'));
        $name = getenv('SNAPSHOT_CONNECTION') ?: 'schema_golden';
        $connection = DB::connection($name);
        $this->freshSchema($connection);

        if ($driver === 'sqlite' && getenv('SNAPSHOT_FIXTURES') === 'crm') {
            DB::setDefaultConnection($name);
            AccountFixture::migrate();
        }
        foreach ($ids as $id) {
            $set = $sets->get($id);
            self::assertContains($driver, $set->drivers);
            $this->schemaMigrator()->migrateSets([$id], $name === 'schema_budget' ? null : $name);
            self::assertSame([], $this->schemaMigrator()->migrateSets([$id], $name === 'schema_budget' ? null : $name));
        }
        $out = (string) getenv('SNAPSHOT_OUT');
        file_put_contents($out, SchemaCatalogue::encode(SchemaCatalogue::of($connection)));

        if (getenv('SNAPSHOT_OBJECTS') === '1') {
            $objects = $driver === 'pgsql' ? [
                'constraints' => $connection->select("SELECT c.relname AS table_name, k.conname AS name, k.contype AS kind, pg_get_constraintdef(k.oid, false) AS definition FROM pg_constraint k JOIN pg_class c ON c.oid = k.conrelid JOIN pg_namespace n ON n.oid = c.relnamespace WHERE n.nspname = 'public' ORDER BY c.relname, k.conname"),
                'indexes' => $connection->select("SELECT c.relname AS table_name, x.relname AS name, pg_get_indexdef(i.indexrelid, 0, false) AS definition, EXISTS (SELECT 1 FROM pg_constraint k WHERE k.conindid = i.indexrelid) AS constraint_backed FROM pg_index i JOIN pg_class c ON c.oid = i.indrelid JOIN pg_class x ON x.oid = i.indexrelid JOIN pg_namespace n ON n.oid = c.relnamespace WHERE n.nspname = 'public' ORDER BY c.relname, x.relname"),
                'comments' => $connection->select("SELECT c.relname AS table_name, a.attname AS column_name, col_description(c.oid, a.attnum) AS comment FROM pg_attribute a JOIN pg_class c ON c.oid = a.attrelid JOIN pg_namespace n ON n.oid = c.relnamespace WHERE n.nspname = 'public' AND a.attnum > 0 AND col_description(c.oid, a.attnum) IS NOT NULL ORDER BY c.relname, a.attname"),
            ] : ['objects' => $connection->select('SELECT tbl_name AS table_name, name, type AS kind, sql AS definition FROM sqlite_master WHERE sql IS NOT NULL ORDER BY tbl_name, name')];
            file_put_contents(str_replace('.json', '-objects.json', $out), json_encode($objects, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n");
        }
        self::assertFileExists($out);
    }
}
