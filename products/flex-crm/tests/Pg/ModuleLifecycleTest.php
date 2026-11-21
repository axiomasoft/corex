<?php

declare(strict_types=1);

use CoreX\Context\Data\AccountRef;
use CoreX\Context\Data\ScopePrincipal;
use CoreX\Data\Data\DataScope;
use CoreX\Foundation\Contracts\LifecycleInventory;
use CoreX\Foundation\Data\ModuleRef;
use CoreX\Operations\Laravel\ApiSchemaConnections;
use CoreX\Tests\Support\LocalWorkContexts;
use CoreX\Tests\Support\WorkScopeFixture;
use Flex\Crm\CrmModule;
use Flex\Crm\Tests\Support\AccountFixture;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Packman\Modules\Contracts\Lifecycle\ModuleLifecycle;
use Packman\Modules\Contracts\Registry\ModuleRegistry;
use Packman\Modules\Enums\Lifecycle\DisableReason;
use Packman\Modules\Internal\Discovery\ModuleDiscovery;
use Packman\Modules\Internal\Support\ModulesOptions;

uses()->group('pg');

it('discovers CRM composer metadata and manages its schema through the standard lifecycle', function (): void {
    config(['database.default' => 'pgsql', 'modules.connection' => 'pgsql']);
    app()->forgetInstance(ModulesOptions::class);
    DB::statement('DROP SCHEMA IF EXISTS public CASCADE');
    DB::statement('CREATE SCHEMA public');
    config(['corex-operations.schema.tenant' => ['connection' => 'pgsql', 'driver' => 'pgsql', 'database' => DB::connection()->getDatabaseName()]]);
    $this->app->forgetInstance(ApiSchemaConnections::class);
    AccountFixture::migrate();
    expect(Schema::hasTable('crm_companies'))->toBeFalse();
    $this->migrateSchemaSets(['axiomasoft/corex:tenant', 'axiomasoft/packman-modules:tenant', 'axiomasoft/corex-operations:tenant', 'axiomasoft/corex-intl:tenant'], DB::getDefaultConnection());
    $this->migrateSchemaSets(['axiomasoft/corex-operations:budget']);

    $consumer = database_path('consumer');
    mkdir($consumer.'/vendor/composer', 0700, true);
    symlink(dirname(__DIR__, 2), $consumer.'/vendor/crm');
    $metadata = json_decode(file_get_contents(__DIR__.'/../../composer.json'), true, flags: JSON_THROW_ON_ERROR);
    $metadata['version'] = 'dev-main';
    $metadata['install-path'] = '../crm';
    file_put_contents($consumer.'/vendor/composer/installed.json', json_encode(['packages' => [$metadata]], JSON_THROW_ON_ERROR));
    $databasePath = database_path();
    $bootstrapPath = app()->bootstrapPath();
    $this->app->setBasePath($consumer);
    $this->app->useDatabasePath($databasePath);
    $this->app->useBootstrapPath($bootstrapPath);
    // P05.3 reads installed.json from the path bound on the container, not from base_path() inside discovery.
    $this->app->forgetInstance(ModuleDiscovery::class);
    $discovered = $this->app->make(ModuleDiscovery::class)->discover();
    expect($discovered)->toHaveCount(1)->and($discovered[0]->composerName)->toBe('flex/crm')
        ->and($metadata['extra']['packman']['module'])->toBe(CrmModule::class);
    $this->artisan('modules:compile')->assertSuccessful();
    $this->app->forgetInstance(ModuleRegistry::class);
    $this->app->forgetInstance(ModuleLifecycle::class);
    $contexts = LocalWorkContexts::install($this->app);
    $contexts->initialize(new AccountRef('crm-account', 'crm', 'active', [], []));
    $scope = WorkScopeFixture::install($this->app, principalId: 'crm-operator')->scope();
    foreach (['inspect', 'enable', 'disable'] as $action) {
        Gate::define('corex.lifecycle.'.$action, static fn (ScopePrincipal $principal, DataScope $current, ModuleRef $module): bool => $principal->kind === 'user' && $principal->id === 'crm-operator' && $current == $scope && $module->package === 'flex/crm');
    }
    $this->app->instance(LifecycleInventory::class, new readonly class($scope) implements LifecycleInventory
    {
        public function __construct(private DataScope $account) {}

        public function scopes(DataScope $operator, ModuleRef $target): array
        {
            if ($operator != $this->account || DB::table('wsp_workspaces')->exists()) {
                throw new LogicException('Unexpected CRM fixture workspace roster.');
            }

            return [$this->account];
        }

        public function owners(DataScope $scope, ModuleRef $target): array
        {
            return [];
        }

        public function revision(DataScope $operator, ModuleRef $target): string
        {
            return 'crm-single-account';
        }

        public function run(DataScope $scope, Closure $operation): mixed
        {
            if ($scope != $this->account) {
                throw new LogicException('Foreign CRM fixture scope.');
            }

            return $operation();
        }
    });
    $lifecycle = resolve(ModuleLifecycle::class);
    $lifecycle->enable('flex/crm');
    foreach (['crm_sources', 'crm_companies', 'crm_contacts', 'crm_contact_company', 'crm_pipelines', 'crm_stages', 'crm_deals', 'crm_leads', 'crm_activities'] as $table) {
        expect(Schema::hasTable($table))->toBeTrue();
    }
    $migrations = DB::table('migrations')->count();
    $id = (string) Str::uuid7();
    DB::table('crm_companies')->insert(['id' => $id, 'name' => 'Preserved', 'created_by' => json_encode(['source' => 'system', 'id' => 'lifecycle-test', 'name' => 'Lifecycle fixture'], JSON_THROW_ON_ERROR)]);
    expect(Schema::hasTable('crm_activities'))->toBeTrue()
        ->and(DB::table('mod_records')->where('module_name', 'flex/crm')->count())->toBeGreaterThan(0);
    $lifecycle->disable('flex/crm', DisableReason::Manual);
    expect(Schema::hasTable('crm_activities'))->toBeTrue();
    $lifecycle->enable('flex/crm');
    expect(DB::table('mod_modules')->where('name', 'flex/crm')->value('state'))->toBe('enabled');
    expect(DB::table('crm_companies')->where('id', $id)->value('name'))->toBe('Preserved')->and(DB::table('migrations')->count())->toBe($migrations);
    $lifecycle->enable('flex/crm');
    expect(DB::table('migrations')->count())->toBe($migrations);
});
