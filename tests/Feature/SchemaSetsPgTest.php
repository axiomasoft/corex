<?php

declare(strict_types=1);

use CoreX\Context\Contracts\TenantContextResolver;
use CoreX\Context\Data\AccountRef;
use CoreX\Context\Data\TenantContext;
use CoreX\Database\Contracts\SchemaMigrator;
use CoreX\Database\Contracts\SchemaSets;
use CoreX\Database\Data\SchemaSet;
use CoreX\Database\Enums\SchemaLifecycle;
use CoreX\Database\Enums\SchemaScope;
use CoreX\Database\Internal\CoreSchemaSets;
use CoreX\Database\Internal\TaggedSchemaSets;
use CoreX\Modules\Contracts\ModuleLifecycle;
use CoreX\Modules\Contracts\ModuleRegistry;
use CoreX\Modules\Data\CompiledModule;
use CoreX\Modules\Internal\Discovery\ModuleDiscovery;
use CoreX\Modules\Internal\Support\ModulesOptions;
use CoreX\Modules\ModulesServiceProvider;
use CoreX\Panels\PanelsServiceProvider;
use CoreX\Tenancy\Internal\TenancySchemaSets;
use CoreX\Tests\Fixtures\Schema\FixtureModule;
use CoreX\Tests\Fixtures\Schema\FixtureSchemaSets;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

uses()->group('pg');

require_once __DIR__.'/../Fixtures/Schema/FixtureSchemaSets.php';
require_once __DIR__.'/../Fixtures/Schema/FixtureModule.php';

beforeEach(function (): void {
    DB::connection('pgsql')->statement('DROP SCHEMA IF EXISTS public CASCADE');
    DB::connection('pgsql')->statement('CREATE SCHEMA public');
    DB::purge('pgsql');
});

it('migrates the complete core tenant baseline from its single platform set', function (): void {
    $this->migrateSchemaSets(['axiomasoft/corex:tenant'], 'pgsql');
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
    expect(app(SchemaMigrator::class)->migrate(SchemaScope::Tenant, 'pgsql'))->toBe([]);
});

it('includes a real module schema only when module execution is requested', function (): void {
    app()->register(PanelsServiceProvider::class);
    app()->forgetInstance(SchemaSets::class);
    app()->forgetInstance(SchemaMigrator::class);
    $this->migrateSchemaScope(SchemaScope::Tenant, 'pgsql', includeModules: false);
    expect(Schema::connection('pgsql')->hasTable('sys_saved_views'))->toBeFalse();
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
    foreach (['first', 'second'] as $action) {
        DB::connection('pgsql')->table('sys_audit_log')->insert([
            'id' => (string) Str::uuid7(), 'actor_type' => 'system', 'action' => $action,
        ]);
    }
    $seqs = DB::connection('pgsql')->table('sys_audit_log')->orderBy('seq')->pluck('seq');
    expect($seqs)->toHaveCount(2)
        ->and((int) $seqs[0])->toBeGreaterThan(0)
        ->and((int) $seqs[1])->toBeGreaterThan((int) $seqs[0]);
});

it('reads the PostgreSQL journal and reports pending files on the selected connection', function (): void {
    $this->migrateSchemaScope(SchemaScope::Tenant, 'pgsql', includeModules: false);
    foreach (app(SchemaMigrator::class)->status(SchemaScope::Tenant, 'pgsql') as $status) {
        expect($status->pending)->toBe([])->and($status->unknown)->toBe([]);
    }
    DB::connection('pgsql')->table('migrations')->where('migration', '2026_10_01_010040_create_sys_settings')->delete();
    $this->artisan('corex:schema:status', ['--database' => 'pgsql'])->assertExitCode(1);
});

it('ignores central legacy journal entries when tenant sets share one database', function (): void {
    $registry = new TaggedSchemaSets([new FixtureSchemaSets([(new CoreSchemaSets)->sets()[0], (new TenancySchemaSets)->sets()[0]])]);
    app()->instance(SchemaSets::class, $registry);
    app()->forgetInstance(SchemaMigrator::class);
    $this->migrateSchemaSets(['axiomasoft/corex-tenancy:root'], 'pgsql');
    $this->migrateSchemaSets(['axiomasoft/corex:tenant'], 'pgsql');
    $status = app(SchemaMigrator::class)->status(SchemaScope::Tenant, 'pgsql')[0];
    expect($status->unknown)->toBe([])->and($status->pending)->toBe([]);
    $this->artisan('corex:schema:status', ['--database' => 'pgsql'])->assertSuccessful();
});

it('enables a module using only its registered schema set path', function (): void {
    config(['database.default' => 'pgsql', 'corex.modules.connection' => 'pgsql']);
    DB::connection('pgsql')->statement('DROP SCHEMA IF EXISTS public CASCADE');
    DB::connection('pgsql')->statement('CREATE SCHEMA public');
    DB::purge('pgsql');
    app()->forgetInstance(ModulesOptions::class);
    app()->register(ModulesServiceProvider::class);
    $coreSets = new CoreSchemaSets;
    app()->instance(SchemaSets::class, new TaggedSchemaSets([new FixtureSchemaSets([
        $coreSets->sets()[0],
        new SchemaSet('fixture/module', 'tenant', SchemaScope::Tenant, realpath(__DIR__.'/../Fixtures/Schema/module'), 1, ['pgsql', 'sqlite'], SchemaLifecycle::Module),
    ])]));
    app()->forgetInstance(SchemaMigrator::class);
    $this->migrateSchemaSets(['axiomasoft/corex:tenant'], 'pgsql');
    app()->instance(ModuleDiscovery::class, new ModuleDiscovery(fallbackModules: [FixtureModule::class]));
    $registry = Mockery::mock(ModuleRegistry::class);
    $registry->shouldReceive('get')->with('fixture/module')->andReturn(new CompiledModule('fixture/module', 'Schema fixture', 'scm', [], [], []));
    app()->instance(ModuleRegistry::class, $registry);
    $resolver = Mockery::mock(TenantContextResolver::class);
    $resolver->shouldReceive('current')->andReturn(new TenantContext(new AccountRef('schema-account', 'schema-account', 'active', [], [])));
    app()->instance(TenantContextResolver::class, $resolver);
    app()->forgetInstance('corex.modules.wiring');
    app()->forgetInstance(ModuleLifecycle::class);
    expect(Schema::connection('pgsql')->hasTable('schema_module'))->toBeFalse();
    app(ModuleLifecycle::class)->enable('fixture/module');
    expect(Schema::connection('pgsql')->hasTable('schema_module'))->toBeTrue()
        ->and(DB::connection('pgsql')->table('mod_modules')->where('name', 'fixture/module')->value('state'))->toBe('enabled')
        ->and(DB::connection('pgsql')->table('migrations')->where('migration', '2026_10_01_980030_create_schema_module')->exists())->toBeTrue();
})->group('pg');
