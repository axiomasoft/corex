<?php

declare(strict_types=1);
use CoreX\Config\Data\CoreOptions;
use CoreX\Database\Contracts\SchemaMigrator;
use CoreX\Database\Contracts\SchemaSets;
use CoreX\Database\Enums\SchemaScope;
use CoreX\Tests\Golden\GoldenTestCase;
use Illuminate\Support\Facades\DB;

$workspace = '/home/vostrikov/projects/packages/corex';
$source = getenv('WITNESS_SOURCE') ?: __DIR__.'/source';
$database = getenv('PGSQL_DATABASE');

if (! is_string($database) || ! preg_match('/^corex_p082_schema_(before|after)_(central|tenant)_test$/', $database)) {
    throw new RuntimeException('Dedicated witness test database required.');
}
putenv('APP_ENV=testing');
$_SERVER['APP_ENV'] = 'testing';
$_ENV['APP_ENV'] = 'testing';
$loader = require $workspace.'/vendor/autoload.php';
$classmap = [];
foreach ($loader->getClassMap() as $name => $file) {
    $path = realpath($file);

    if ($path !== false && str_starts_with($path, $workspace.'/')) {
        $relative = substr($path, strlen($workspace) + 1);

        if (is_file($source.'/'.$relative)) {
            $classmap[$name] = $source.'/'.$relative;
        }
    }
}
$loader->addClassMap($classmap);
foreach (array_merge([$source.'/composer.json'], glob($source.'/packages/*/composer.json'), glob($source.'/products/*/composer.json')) as $manifest) {
    $json = json_decode(file_get_contents($manifest), true, flags: JSON_THROW_ON_ERROR);
    foreach (['autoload', 'autoload-dev'] as $kind) {
        foreach ($json[$kind]['psr-4'] ?? [] as $namespace => $paths) {
            foreach ((array) $paths as $path) {
                $loader->addPsr4($namespace, dirname($manifest).'/'.$path, true);
            }
        }
    }
}
require_once $source.'/tests/Golden/GoldenTestCase.php';
class CatalogWitness extends GoldenTestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $app['config']->set('database.connections.witness_budget', ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
        $app['config']->set('corex-operations.schema', [
            'budget' => ['connection' => 'witness_budget', 'driver' => 'sqlite', 'database' => ':memory:'],
            'tenant' => ['connection' => 'pgsql', 'driver' => 'pgsql', 'database' => getenv('PGSQL_DATABASE')],
        ]);
    }

    public function boot(): void
    {
        parent::setUp();
    }

    public function finish(): void
    {
        parent::tearDown();
    }

    public function capture(string $database, string $scope): array
    {
        $this->app['config']->set('database.default', 'pgsql');
        $this->app['config']->set('database.connections.pgsql.database', $database);
        DB::purge('pgsql');
        $db = DB::connection('pgsql');

        if ($db->getDatabaseName() !== $database) {
            throw new RuntimeException('Database mismatch');
        }

        if ($db->selectOne("SELECT count(*) AS n FROM pg_tables WHERE schemaname='public'")->n !== 0) {
            throw new RuntimeException('Witness database is not pristine');
        }
        $registry = $this->app->make(SchemaSets::class);
        $selection = $registry->forScope(SchemaScope::from($scope), true);
        $sets = array_map(fn ($s) => $s->id(), $selection);
        $migrationFiles = [];
        foreach ($selection as $set) {
            foreach (glob($set->path.'/*_*.php') as $file) {
                $migrationFiles[] = basename($file);
            }
        }
        sort($migrationFiles);
        $migrated = $this->app->make(SchemaMigrator::class)->migrate(SchemaScope::from($scope), 'pgsql', true);
        $columns = $db->select(<<<'SQL'
SELECT c.relname AS table_name,a.attnum AS ordinal,a.attname AS column_name,
       pg_catalog.format_type(a.atttypid,a.atttypmod) AS column_type,
       a.attnotnull AS not_null,a.attidentity AS identity_kind,a.attgenerated AS generated_kind,
       pg_get_expr(d.adbin,d.adrelid) AS default_expression
FROM pg_class c JOIN pg_namespace n ON n.oid=c.relnamespace
JOIN pg_attribute a ON a.attrelid=c.oid
LEFT JOIN pg_attrdef d ON d.adrelid=c.oid AND d.adnum=a.attnum
WHERE n.nspname='public' AND c.relkind IN ('r','p') AND a.attnum>0 AND NOT a.attisdropped
ORDER BY c.relname,a.attnum
SQL);
        $constraints = $db->select(<<<'SQL'
SELECT c.relname AS table_name,k.conname AS constraint_name,k.contype AS constraint_type,
       pg_get_constraintdef(k.oid,true) AS definition,k.condeferrable AS deferrable,k.condeferred AS initially_deferred
FROM pg_constraint k JOIN pg_class c ON c.oid=k.conrelid JOIN pg_namespace n ON n.oid=c.relnamespace
WHERE n.nspname='public' ORDER BY c.relname,k.conname
SQL);
        $indexes = $db->select("SELECT tablename AS table_name,indexname AS index_name,indexdef AS definition FROM pg_indexes WHERE schemaname='public' ORDER BY tablename,indexname");

        return ['scope' => $scope, 'sets' => $sets, 'migration_files' => $migrationFiles, 'migrated' => $migrated, 'columns' => $columns, 'constraints' => $constraints, 'indexes' => $indexes];
    }
}
$harness = new CatalogWitness('witness');

try {
    $harness->boot();
    $result = $harness->capture($database, getenv('WITNESS_SCOPE') ?: 'tenant');
    $result['source'] = realpath($source);
    $result['core_options_source'] = (new ReflectionClass(CoreOptions::class))->getFileName();
    file_put_contents(getenv('WITNESS_OUTPUT') ?: __DIR__.'/catalog.json', json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n");
    echo 'Witness GREEN: '.count($result['migration_files']).' migrations, '.count($result['columns'])." columns\n";
} catch (Throwable $e) {
    fwrite(STDERR, (string) $e);
    exit(1);
}
