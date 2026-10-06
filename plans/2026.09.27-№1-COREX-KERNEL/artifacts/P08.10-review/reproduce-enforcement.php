<?php

declare(strict_types=1);

$repo = getcwd();
require $repo.'/vendor/autoload.php';
require $repo.'/scripts/schema-gate.php';
require_once $repo.'/tests/Golden/Support/SchemaCatalogue.php';

// Isolated static fixture: shipped product files are never edited or executed.
$root = sys_get_temp_dir().'/corex-p0810-review-'.bin2hex(random_bytes(8));
$path = 'packages/core/database/migrations/tenant';
$fs = new Illuminate\Filesystem\Filesystem;
try {
    $fs->makeDirectory($root.'/'.$path, 0700, true);
    $fs->makeDirectory($root.'/docs/architecture', 0700, true);
    file_put_contents($root.'/docs/architecture/table-ownership.md', "| sys_gate_probe | axiomasoft/corex | tenant |\n");
    $file = $root.'/'.$path.'/2026_10_01_010010_create_sys_gate_probe.php';
    $manifest = ['packages' => ['axiomasoft/corex' => '01'], 'sets' => [['id' => 'axiomasoft/corex:tenant', 'package' => 'axiomasoft/corex', 'name' => 'tenant', 'scope' => 'tenant', 'path' => $path, 'version' => 1, 'drivers' => ['pgsql'], 'lifecycle' => 'platform', 'requires' => [], 'connection' => null]]];
    foreach (['bigint', 'integer'] as $type) {
        $source = "<?php\nuse Illuminate\\Database\\Migrations\\Migration;\nuse Illuminate\\Support\\Facades\\DB;\nreturn new class extends Migration { public function up(): void { DB::statement('CREATE TABLE sys_gate_probe (id uuid NOT NULL, attempts $type NOT NULL, CONSTRAINT sys_gate_probe_pk PRIMARY KEY (id))'); } public function down(): void {} };\n";
        file_put_contents($file, $source);
        $hits = (new SchemaGate($root, $manifest, []))->run();
        echo json_encode(['probe' => 'raw-sql-S6', 'column_type' => $type, 'allowlist' => [], 'hits' => $hits], JSON_THROW_ON_ERROR)."\n";
    }
} finally {
    $fs->deleteDirectory($root);
}

// Same isolated endpoint as the completed PG validations, no other suites running.
putenv('APP_ENV=testing');
$db = new Illuminate\Database\Capsule\Manager;
$db->addConnection(['driver' => 'pgsql', 'host' => '127.0.0.1', 'port' => '5434', 'database' => 'corex_test', 'username' => 'corex', 'password' => 'corex', 'charset' => 'utf8', 'prefix' => '', 'schema' => 'public']);
$c = $db->getConnection();
if (! preg_match('/^[a-z0-9_]+_test$/', $c->getDatabaseName()) || $c->selectOne('SELECT current_database() AS name')->name !== 'corex_test') {
    throw new RuntimeException('Wrong test database.');
}
$c->beginTransaction();
try {
    $c->statement('CREATE TABLE p0810_review_probe (workspace_id uuid, code text)');
    $c->statement('CREATE UNIQUE INDEX p0810_review_probe_uq ON p0810_review_probe (workspace_id, code) NULLS NOT DISTINCT');
    $a = CoreX\Tests\Golden\Support\SchemaCatalogue::of($c)['tables']['p0810_review_probe'];
    $actualA = $c->selectOne("SELECT pg_get_indexdef('p0810_review_probe_uq'::regclass) AS definition")->definition;
    $c->statement('DROP INDEX p0810_review_probe_uq');
    $c->statement('CREATE UNIQUE INDEX p0810_review_probe_uq ON p0810_review_probe (workspace_id, code)');
    $b = CoreX\Tests\Golden\Support\SchemaCatalogue::of($c)['tables']['p0810_review_probe'];
    $actualB = $c->selectOne("SELECT pg_get_indexdef('p0810_review_probe_uq'::regclass) AS definition")->definition;
    echo json_encode(['probe' => 'golden-null-uniqueness', 'catalogues_equal' => $a === $b, 'before_ddl' => $actualA, 'after_ddl' => $actualB, 'catalogue_index' => $a['indexes']], JSON_THROW_ON_ERROR)."\n";
    $c->insert("INSERT INTO p0810_review_probe VALUES (NULL, 'same'), (NULL, 'same')");
    echo json_encode(['probe' => 'changed-behavior', 'duplicate_global_keys_accepted' => $c->table('p0810_review_probe')->count()], JSON_THROW_ON_ERROR)."\n";
} finally {
    $c->rollBack();
}
echo json_encode(['probe' => 'rollback', 'remaining_table' => $c->selectOne("SELECT to_regclass('public.p0810_review_probe') AS name")->name], JSON_THROW_ON_ERROR)."\n";
