<?php

declare(strict_types=1);

use CoreX\Access\Internal\AccessSchemaSets;
use CoreX\Audit\Data\AuditEntry;
use CoreX\Audit\Enums\ActorType;
use CoreX\Audit\Internal\QueuedAuditLogger;
use CoreX\Config\Data\CoreOptions;
use CoreX\Database\Internal\CoreSchemaSets;
use CoreX\Panels\PanelsServiceProvider;
use CoreX\Tenancy\Internal\TenancySchemaSets;
use CoreX\Tests\Fixtures\Schema\FixtureSchemaSets;
use CoreX\Tests\Support\WorkScopeFixture;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Packman\Schema\Contracts\Migration\SchemaMigrator;
use Packman\Schema\Contracts\Migration\SchemaSets;
use Packman\Schema\Enums\SchemaScope;
use Packman\Schema\Internal\Migration\TaggedSchemaSets;

uses()->group('pg');

require_once __DIR__.'/../Fixtures/Schema/FixtureSchemaSets.php';
require_once __DIR__.'/../Fixtures/Schema/FixtureModule.php';

beforeEach(function (): void {
    DB::connection('pgsql')->statement('DROP SCHEMA IF EXISTS public CASCADE');
    DB::connection('pgsql')->statement('CREATE SCHEMA public');
    DB::purge('pgsql');
});

it('migrates the access cloud constraints and their transitive tenant prerequisites from an empty schema', function (): void {
    app()->instance(SchemaSets::class, new TaggedSchemaSets([new AccessSchemaSets, new TenancySchemaSets, new CoreSchemaSets]));
    app()->forgetInstance(SchemaMigrator::class);

    $this->migrateSchemaSets(['axiomasoft/corex-access:tenant-cloud'], 'pgsql');

    $constraint = DB::connection('pgsql')->selectOne("select confrelid::regclass::text as target from pg_constraint where conname = 'aut_role_assignments_workspace_fk'");
    expect($constraint?->target)->toBe('wsp_workspaces')
        ->and(Schema::connection('pgsql')->hasTable('sys_department_user'))->toBeTrue()
        ->and(Schema::connection('pgsql')->hasTable('root_accounts'))->toBeFalse()
        ->and(resolve(SchemaMigrator::class)->migrateSets(['axiomasoft/corex-access:tenant-cloud'], 'pgsql'))->toBe([]);
});

it('migrates the domain and upstream module platform sets with distinct ownership', function (): void {
    $this->migrateSchemaSets(['axiomasoft/corex:tenant', 'axiomasoft/packman-modules:tenant'], 'pgsql');
    expect(Schema::connection('pgsql')->hasTable('sys_settings'))->toBeTrue()
        ->and(Schema::connection('pgsql')->hasTable('sys_audit_log'))->toBeTrue()
        ->and(Schema::connection('pgsql')->hasTable('sys_feature_flags'))->toBeTrue()
        ->and(Schema::connection('pgsql')->hasTable('sys_departments'))->toBeTrue()
        ->and(Schema::connection('pgsql')->hasTable('sys_pubsub_cursors'))->toBeTrue()
        ->and(Schema::connection('pgsql')->hasTable('sys_outbox_messages'))->toBeTrue()
        ->and(Schema::connection('pgsql')->hasTable('sys_inbox_messages'))->toBeTrue()
        ->and(Schema::connection('pgsql')->hasTable('mod_modules'))->toBeTrue()
        ->and(Schema::connection('pgsql')->hasTable('mod_records'))->toBeTrue()
        ->and(Schema::connection('pgsql')->hasTable('mod_lifecycle_log'))->toBeTrue()
        ->and(Schema::connection('pgsql')->hasTable('root_accounts'))->toBeFalse();
    expect(resolve(SchemaMigrator::class)->migrate(SchemaScope::Tenant, 'pgsql'))->toBe([]);
});

it('registers upstream saved-view storage as platform infrastructure', function (): void {
    app()->register(PanelsServiceProvider::class);
    app()->forgetInstance(SchemaSets::class);
    app()->forgetInstance(SchemaMigrator::class);
    $this->migrateSchemaScope(SchemaScope::Tenant, 'pgsql', includeModules: false);
    expect(Schema::connection('pgsql')->hasTable('sys_saved_views'))->toBeTrue();
    $this->migrateSchemaScope(SchemaScope::Tenant, 'pgsql', includeModules: true);
    expect(Schema::connection('pgsql')->hasTable('sys_saved_views'))->toBeTrue()
        ->and(Schema::connection('pgsql')->hasTable('sys_saved_view_scopes'))->toBeTrue();
});

it('preserves explicit microsecond precision on every sys and module timestamp (D32)', function (): void {
    $this->migrateSchemaScope(SchemaScope::Tenant, 'pgsql');
    /** @var list<stdClass> $columns */
    $columns = DB::connection('pgsql')->select(<<<'SQL'
        SELECT table_name, column_name, datetime_precision
        FROM information_schema.columns
        WHERE table_schema = 'public'
          AND data_type = 'timestamp with time zone'
          AND (table_name LIKE 'sys\_%' OR table_name LIKE 'mod\_%')
        ORDER BY table_name, column_name
        SQL);
    expect($columns)->not->toBeEmpty();
    foreach ($columns as $column) {
        expect((int) $column->datetime_precision)->toBe(6);
    }
});

it('preserves the monotonic audit sequence cursor (D42)', function (): void {
    $this->migrateSchemaSets(['axiomasoft/corex:tenant'], 'pgsql');
    config(['corex.audit.enabled' => true]);
    app()->forgetInstance(CoreOptions::class);
    $previous = DB::getDefaultConnection();
    DB::setDefaultConnection('pgsql');

    try {
        WorkScopeFixture::install($this->app, 'schema-audit-test', 'service')->scope();
        $logger = app()->make(QueuedAuditLogger::class);
        foreach (['first', 'second'] as $action) {
            $logger->record(new AuditEntry(action: $action, actorType: ActorType::System));
        }
        $seqs = DB::connection('pgsql')->table('sys_audit_log')->orderBy('seq')->pluck('seq');
        expect($seqs)->toHaveCount(2)
            ->and((int) $seqs[0])->toBeGreaterThan(0)
            ->and((int) $seqs[1])->toBeGreaterThan((int) $seqs[0]);
    } finally {
        DB::setDefaultConnection($previous);
    }
});

it('reads the PostgreSQL journal and reports pending files on the selected connection', function (): void {
    $this->migrateSchemaScope(SchemaScope::Tenant, 'pgsql', includeModules: false);
    foreach (resolve(SchemaMigrator::class)->status(SchemaScope::Tenant, 'pgsql') as $status) {
        expect($status->pending)->toBe([])->and($status->unknown)->toBe([]);
    }
    DB::connection('pgsql')->table('migrations')->where('migration', '2026_10_01_010040_create_sys_settings')->delete();
    $this->artisan('packman:schema:status', ['--database' => 'pgsql'])->assertExitCode(1);
});

it('ignores central legacy journal entries when tenant sets share one database', function (): void {
    $registry = new TaggedSchemaSets([new FixtureSchemaSets([(new CoreSchemaSets)->sets()[0], (new TenancySchemaSets)->sets()[0]])]);
    app()->instance(SchemaSets::class, $registry);
    app()->forgetInstance(SchemaMigrator::class);
    $this->migrateSchemaSets(['axiomasoft/corex-tenancy:root'], 'pgsql');
    $this->migrateSchemaSets(['axiomasoft/corex:tenant'], 'pgsql');
    $status = resolve(SchemaMigrator::class)->status(SchemaScope::Tenant, 'pgsql')[0];
    expect($status->unknown)->toBe([])->and($status->pending)->toBe([]);
    $this->artisan('packman:schema:status', ['--database' => 'pgsql'])->assertSuccessful();
});
