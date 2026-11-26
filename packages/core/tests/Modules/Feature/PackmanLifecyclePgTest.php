<?php

declare(strict_types=1);

use CoreX\Context\Contracts\ControlStoreIsolation;
use CoreX\Context\Contracts\TenantContextResolver;
use CoreX\Context\Data\AccountRef;
use CoreX\Context\Data\ScopePrincipal;
use CoreX\Context\Data\WorkspaceRef;
use CoreX\Context\Internal\LocalWorkTransactions;
use CoreX\Context\Laravel\DatabaseScopeControl;
use CoreX\Context\Laravel\ScopeControlSchema;
use CoreX\Context\Laravel\ScopeOptions;
use CoreX\Data\Contracts\DataScopeCodec;
use CoreX\Data\Data\DataScope;
use CoreX\Data\Laravel\JsonDocuments;
use CoreX\Features\Contracts\FeatureFlags;
use CoreX\Foundation\Contracts\LifecycleInventory;
use CoreX\Foundation\Contracts\LifecyclePlans;
use CoreX\Foundation\Contracts\ModuleReferences;
use CoreX\Foundation\Contracts\ReferenceAdmission;
use CoreX\Foundation\Contracts\ReferenceOwners;
use CoreX\Foundation\Data\LifecyclePlanRef;
use CoreX\Foundation\Data\ModuleId;
use CoreX\Foundation\Data\ModuleRef;
use CoreX\Foundation\Data\PlanDigest;
use CoreX\Foundation\Enums\LifecycleAction;
use CoreX\Foundation\Enums\LifecyclePhase;
use CoreX\Foundation\Exceptions\LifecycleBlocked;
use CoreX\Foundation\Exceptions\LifecyclePlanConflict;
use CoreX\Settings\Contracts\SettingsRepository;
use CoreX\Tests\Modules\Fixtures\CommittedCleanupOwner;
use CoreX\Tests\Support\LocalWorkContexts;
use CoreX\Tests\Support\WorkScopeFixture;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Connection;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Packman\Context\Contracts\ContextScopeInterface;
use Packman\Extensions\Contracts\Configuration\WithDatabaseSettings;
use Packman\Features\Data\FeatureFlagDefinition;
use Packman\Modules\Contracts\Definition\ModuleDefinition;
use Packman\Modules\Contracts\Lifecycle\ModuleActivationGate;
use Packman\Modules\Contracts\Lifecycle\ModuleLifecycle;
use Packman\Modules\Contracts\Registry\ModuleRegistry;
use Packman\Modules\Data\Lifecycle\ModuleActivationDecision;
use Packman\Modules\Data\Lifecycle\ModuleDependency;
use Packman\Modules\Data\Lifecycle\ModuleManifest;
use Packman\Modules\Enums\Lifecycle\DisableReason;
use Packman\Modules\Enums\Lifecycle\ModuleState;
use Packman\Modules\Events\Lifecycle\ModulePurged;
use Packman\Schema\Contracts\Migration\SchemaMigrator;
use Packman\Schema\Contracts\Migration\SchemaSetProvider;
use Packman\Schema\Contracts\Migration\SchemaSets;
use Packman\Schema\Data\Migration\SchemaSet;
use Packman\Schema\Enums\SchemaLifecycle;
use Packman\Schema\Enums\SchemaScope;
use Packman\Settings\Contracts\SettingDefaultsProviderInterface;
use Symfony\Component\Process\Exception\ProcessSignaledException;
use Symfony\Component\Process\Process;

require_once dirname(__DIR__).'/Fixtures/CommittedCleanupOwner.php';

uses()->group('pg');

final class CoreXPackmanIntegrationModule extends ModuleDefinition implements WithDatabaseSettings
{
    public function manifest(): ModuleManifest
    {
        return ModuleManifest::make('fixture/packman-integration')
            ->title('CoreX Packman Integration')
            ->tablePrefix('pmi')
            ->featureFlags([FeatureFlagDefinition::make('pmi.enabled', default: true)->module('fixture/packman-integration')]);
    }

    public function settingsNamespace(): string
    {
        return 'pmi';
    }

    public function defaultSettings(): array
    {
        return ['label' => 'From manifest', 'secret' => 'test-secret'];
    }

    public function sensitiveSettings(): array
    {
        return ['secret'];
    }
}

final class CoreXDependentIntegrationModule extends ModuleDefinition
{
    public function manifest(): ModuleManifest
    {
        return ModuleManifest::make('fixture/packman-dependent')->title('Dependent integration module')
            ->requires([ModuleDependency::module('fixture/packman-integration')]);
    }
}

final class CoreXLateIntegrationModule extends ModuleDefinition
{
    public static bool $requiresRoot = false;

    public function manifest(): ModuleManifest
    {
        return ModuleManifest::make('fixture/packman-late')->title('Late integration module')->requires(self::$requiresRoot
            ? [ModuleDependency::module('fixture/packman-integration')] : []);
    }
}

it('runs schema claims and lifecycle through Packman while CoreX persists feature defaults and settings', function (): void {
    config(['database.default' => 'pgsql']);
    $database = DB::connection('pgsql');
    $expected = getenv('PGSQL_DATABASE') ?: 'corex_test';

    if (! str_ends_with($expected, '_test') || $database->getDatabaseName() !== $expected || ! app()->environment('testing')) {
        throw new RuntimeException('Packman integration requires APP_ENV=testing and the explicit isolated PGSQL_DATABASE ending in _test.');
    }
    $database->statement('DROP SCHEMA IF EXISTS public CASCADE');
    $database->statement('CREATE SCHEMA public');

    $original = $this->app->basePath();
    $temporary = sys_get_temp_dir().'/corex-packman-lifecycle-'.bin2hex(random_bytes(12));
    $files = new Filesystem;
    $files->makeDirectory($temporary.'/vendor/composer', 0700, true);
    $files->makeDirectory($temporary.'/bootstrap/cache', 0700, true);
    $metadata = json_decode(file_get_contents(dirname(__DIR__, 5).'/vendor/composer/installed.json'), true, flags: JSON_THROW_ON_ERROR);
    $metadata['packages'] = array_values(array_filter($metadata['packages'], static fn (array $package): bool => in_array($package['name'], ['axiomasoft/corex', 'axiomasoft/packman-modules'], true)));
    $metadata['packages'][] = [
        'name' => 'fixture/packman-integration', 'version' => '1.0.0', 'type' => 'packman-module',
        'extra' => ['packman' => ['module' => CoreXPackmanIntegrationModule::class]],
    ];
    foreach (['fixture/packman-dependent' => CoreXDependentIntegrationModule::class, 'fixture/packman-late' => CoreXLateIntegrationModule::class] as $name => $class) {
        $metadata['packages'][] = ['name' => $name, 'version' => '1.0.0', 'type' => 'packman-module',
            'require' => ['fixture/packman-integration' => '*'], 'extra' => ['packman' => ['module' => $class]]];
    }
    $files->put($temporary.'/vendor/composer/installed.json', json_encode($metadata, JSON_THROW_ON_ERROR));
    $this->app->setBasePath($temporary);
    $this->app->instance('test.packman.schema', new class implements SchemaSetProvider
    {
        public function sets(): array
        {
            return [new SchemaSet(
                package: 'fixture/packman-integration', name: 'tenant', scope: SchemaScope::Tenant,
                path: dirname(__DIR__, 2).'/Fixtures/Packman/migrations', version: 1,
                lifecycle: SchemaLifecycle::Module,
            )];
        }
    });
    $this->app->tag(['test.packman.schema'], SchemaSetProvider::class);
    $this->app->forgetInstance(SchemaSets::class);
    $this->app->forgetInstance(SchemaMigrator::class);
    $this->app->forgetInstance(ModuleRegistry::class);

    try {
        expect(Artisan::call('modules:compile'))->toBe(0, Artisan::output());
        $schemas = resolve(SchemaMigrator::class);
        $schemas->migrateSets(['axiomasoft/corex:tenant', 'axiomasoft/packman-modules:tenant'], 'pgsql');
        expect(Schema::hasTable('pmi_records'))->toBeFalse();

        $contexts = LocalWorkContexts::install($this->app);
        $contexts->initialize(new AccountRef('A', 'tenant-a', 'active', [], []));
        $contexts->setWorkspace(new WorkspaceRef('W1', 'one', 'workspace', '/', null));
        $host = WorkScopeFixture::install($this->app, principalId: 'operator');
        $workspace = $host->scope();
        $activation = new class implements ModuleActivationGate
        {
            public ?ContextScopeInterface $observed = null;

            public function check(ContextScopeInterface $ctx, string $module, string $edition): ModuleActivationDecision
            {
                $this->observed = $ctx;

                return ModuleActivationDecision::allow();
            }
        };
        $this->app->instance(ModuleActivationGate::class, $activation);
        $lifecycle = resolve(ModuleLifecycle::class);
        $name = 'fixture/packman-integration';
        expect(fn () => $lifecycle->enable($name))->toThrow(AuthorizationException::class);
        $allow = false;
        $grantDependent = false;
        foreach (['enable', 'inspect', 'disable', 'purge', 'upgrade', 'repair', 'detach'] as $action) {
            Gate::define('corex.lifecycle.'.$action, static function (ScopePrincipal $principal, DataScope $scope, ModuleRef $module) use ($workspace, $name, $action, &$allow, &$grantDependent): bool {
                return $allow && $principal->kind === 'user' && $principal->id === 'operator'
                    && $scope->accountId === $workspace->accountId && $scope->bindingId === $workspace->bindingId
                    && $scope->accountIncarnation === $workspace->accountIncarnation && $scope->restoreEpoch === $workspace->restoreEpoch
                    && $scope->bindingGeneration === $workspace->bindingGeneration && $scope->deploymentId === $workspace->deploymentId
                    && in_array($scope->workspaceId, [null, 'W1'], true)
                    && ($module->package === $name || (in_array($module->package, ['fixture/packman-dependent', 'fixture/packman-late'], true)
                        && (in_array($action, ['enable', 'inspect'], true) || ($action === 'disable' && $grantDependent))));
            });
        }
        expect(fn () => $lifecycle->enable($name))->toThrow(AuthorizationException::class)
            ->and(Schema::hasTable('pmi_records'))->toBeFalse();
        $allow = true;
        expect(fn () => $database->transaction(fn () => $lifecycle->enable($name)))->toThrow(LifecycleBlocked::class)
            ->and($lifecycle->status($name)->state)->toBe(ModuleState::Available);
        $lifecycle->enable('fixture/packman-integration');
        $context = resolve(TenantContextResolver::class)->current();
        expect($lifecycle->status('fixture/packman-integration')->state)->toBe(ModuleState::Enabled)
            ->and(Schema::hasTable('pmi_records'))->toBeTrue()
            ->and(resolve(ModuleRegistry::class)->isActive('fixture/packman-integration', $context))->toBeTrue()
            ->and(resolve(FeatureFlags::class)->active('pmi.enabled'))->toBeTrue()
            ->and(JsonDocuments::decode(resolve(SettingsRepository::class)->get('pmi', 'label')))->toBe('From manifest')
            ->and(resolve(SettingDefaultsProviderInterface::class)->isSensitive('pmi', 'secret'))->toBeTrue()
            ->and($database->table('mod_records')->where('module_name', 'fixture/packman-integration')->where('record_type', 'table')->where('reference', 'pmi_records')->exists())->toBeTrue()
            ->and($database->table('mod_lifecycle_log')->value('actor_type'))->toBe('system');

        expect($activation->observed)->toEqual($context)
            ->and($context->workspace?->id)->toBe('W1')
            ->and(fn () => $lifecycle->disable($name, DisableReason::Manual))->toThrow(AuthorizationException::class)
            ->and(fn () => $lifecycle->upgrade($name))->toThrow(AuthorizationException::class)
            ->and(fn () => $lifecycle->repair($name))->toThrow(AuthorizationException::class);
        $contexts->setWorkspace(new WorkspaceRef('W2', 'two', 'workspace', '/', null));
        expect(fn () => $lifecycle->status($name))->toThrow(AuthorizationException::class);
        $contexts->setWorkspace(null);
        $account = $host->scope();
        $this->app->instance(LifecycleInventory::class, new readonly class($account, $workspace, $contexts) implements LifecycleInventory
        {
            public function __construct(private DataScope $account, private DataScope $workspace, private LocalWorkContexts $contexts) {}

            public function scopes(DataScope $operator, ModuleRef $target): array
            {
                return [$this->account, $this->workspace];
            }

            public function owners(DataScope $scope, ModuleRef $target): array
            {
                return [];
            }

            public function revision(DataScope $operator, ModuleRef $target): string
            {
                return 'fixture-A-W1:v1';
            }

            public function run(DataScope $scope, Closure $operation): mixed
            {
                return $this->contexts->runFor($this->contexts->account($scope->accountId), $scope->workspaceId === null ? null : $this->contexts->workspace($scope->workspaceId), $operation);
            }
        });
        $lifecycle->enable('fixture/packman-late');
        $interrupt = true;
        $database->listen(static function (QueryExecuted $event) use (&$interrupt): void {
            if ($interrupt && str_contains($event->sql, 'update "sys_lifecycle_plans" set "digest"') && in_array('fenced', $event->bindings, true)) {
                $interrupt = false;

                throw new RuntimeException('Stop after committed plan.');
            }
        });
        expect(fn () => $lifecycle->disable($name, DisableReason::Manual, cascade: true))->toThrow(RuntimeException::class, 'Stop after committed plan.');
        $saved = $database->table('sys_lifecycle_plans')->where('module', $name)->sole();
        $ref = new LifecyclePlanRef($saved->plan_id);
        $digest = new PlanDigest($saved->digest);
        $plans = resolve(LifecyclePlans::class);
        $control = resolve(DatabaseScopeControl::class);
        expect(json_decode($saved->arguments, true, flags: JSON_THROW_ON_ERROR)['targets'])->toBe([$name])
            ->and($control->moduleClosure($account, new ModuleRef($name)))->toBe($ref->id)
            ->and(fn () => $lifecycle->enable('fixture/packman-dependent'))->toThrow(LifecycleBlocked::class)
            ->and(fn () => $lifecycle->enable('fixture/packman-dependent', cascade: true))->toThrow(LifecycleBlocked::class)
            ->and($lifecycle->status('fixture/packman-dependent')->state)->toBe(ModuleState::Available);
        // An already enabled host contribution changes its live declaration after planning.
        // Public Packman consults discovery again when recursively disabling dependents.
        CoreXLateIntegrationModule::$requiresRoot = true;
        expect(fn () => $lifecycle->disable('fixture/packman-late', DisableReason::Manual))->toThrow(AuthorizationException::class)
            ->and(fn () => $plans->apply($account, $ref, $digest))->toThrow(LifecyclePlanConflict::class)
            ->and($lifecycle->status('fixture/packman-late')->state)->toBe(ModuleState::Enabled)
            ->and($lifecycle->status($name)->state)->toBe(ModuleState::Enabled)
            ->and($control->moduleClosure($account, new ModuleRef('fixture/packman-late')))->toBeNull();
        CoreXLateIntegrationModule::$requiresRoot = false;
        expect($plans->apply($account, $ref, $digest)->phase)->toBe(LifecyclePhase::Done);

        expect(resolve(ModuleRegistry::class)->isActive('fixture/packman-integration', $context))->toBeFalse();
        expect(fn () => $lifecycle->uninstall($name, keepData: false))->toThrow(LifecycleBlocked::class);
        $plans = resolve(LifecyclePlans::class);
        $plan = $plans->plan($account, new ModuleId($name), LifecycleAction::Purge);
        $blocked = $plans->apply($account, $plan->ref, $plan->digest);
        expect($blocked->phase)->toBe(LifecyclePhase::Blocked)
            ->and(array_column($blocked->blockers, 'resourceKind'))->toContain('sys_feature_flags');
        // Fixture administrator retires the one Core-owned flag on the disabled module.
        $flag = collect($blocked->blockers)->firstWhere('resourceKind', 'sys_feature_flags');
        expect($database->table('sys_feature_flags')->where('id', $flag->resourceId)->where('module', $name)->delete())->toBe(1);
        $externalDatabase = getenv('COREX_LIFECYCLE_CONTROL_DATABASE') ?: preg_replace('/_test$/', '_control_test', $expected);

        if (! is_string($externalDatabase) || ! str_ends_with($externalDatabase, '_test') || $externalDatabase === $expected) {
            throw new RuntimeException('An independent external test database is required.');
        }
        config(['database.connections.external_record_test' => [...config('database.connections.pgsql'), 'database' => $externalDatabase],
            'database.connections.external_observer_test' => [...config('database.connections.pgsql'), 'database' => $externalDatabase]]);
        $external = DB::connection('external_record_test');
        $observer = DB::connection('external_observer_test');
        $table = 'cleanup_'.bin2hex(random_bytes(10));
        expect($external->scalar('select current_database()'))->toBe($externalDatabase);
        $external->statement('create table '.$table.' (id text primary key, value text not null)');

        try {
            $receiptOwner = new CommittedCleanupOwner($external, $table, $account);
            $this->app->instance('test.external-records', $receiptOwner);
            $this->app->tag('test.external-records', ModuleReferences::class);
            $plan = $plans->plan($account, new ModuleId($name), LifecycleAction::Purge);
            $lost = $plans->apply($account, $plan->ref, $plan->digest);
            $originalEvidence = $observer->table($table)->where('id', $receiptOwner->action->effectKey)->sole()->value;
            expect($lost->phase)->toBe(LifecyclePhase::Blocked)
                ->and($lost->blockers[0]->resourceId)->toBe($receiptOwner->resource)
                ->and($lost->receipts)->toBe([])
                ->and($observer->table($table)->where('id', $receiptOwner->resource)->exists())->toBeFalse()
                ->and($originalEvidence)->toBe('original-content/'.$plan->ref->id);
            $receiptOwner->reachable = false;
            expect($plans->apply($account, $plan->ref, $plan->digest)->phase)->toBe(LifecyclePhase::Blocked)
                ->and($plans->plan($account, new ModuleId($name), LifecycleAction::Purge))->toEqual($plan);
            $receiptOwner->reachable = true;
            $resumed = $plans->plan($account, new ModuleId($name), LifecycleAction::Purge);
            $failBeforeCommit = true;
            $database->listen(static function (QueryExecuted $event) use (&$failBeforeCommit): void {
                if ($failBeforeCommit && str_contains($event->sql, 'update "sys_lifecycle_plans" set "state"') && in_array('purged', $event->bindings, true)) {
                    $failBeforeCommit = false;

                    throw new RuntimeException('Stop before the purge transaction commits.');
                }
            });
            $rolledBack = $plans->apply($account, $resumed->ref, $resumed->digest);
            expect($rolledBack->phase)->toBe(LifecyclePhase::Blocked)
                ->and($rolledBack->transitionResult)->toBeNull()
                ->and($rolledBack->receipts[0]->evidence)->toBe($originalEvidence)
                ->and($lifecycle->status($name)->state)->toBe(ModuleState::Disabled)
                ->and(Schema::hasTable('pmi_records'))->toBeTrue()
                ->and($observer->table($table)->where('value', $plan->ref->id)->count())->toBeGreaterThan(0);
            $listenerFailure = new RuntimeException('Native purge subscriber failed after commit.');
            $notifications = 0;
            $releaseAckLost = false;
            $external->listen(static function (QueryExecuted $event) use (&$releaseAckLost, $external, $plan, $table): void {
                if (! $releaseAckLost && $event->connection === $external && str_starts_with($event->sql, 'delete from "'.$table.'"')
                    && in_array($plan->ref->id, $event->bindings, true)) {
                    $releaseAckLost = true;

                    throw new RuntimeException('Owner lease release committed before acknowledgement.');
                }
            });
            resolve('events')->listen(ModulePurged::class, static function (ModulePurged $event) use ($name, $listenerFailure, &$notifications): void {
                if ($event->module === $name) {
                    $notifications++;

                    throw $listenerFailure;
                }
            });
            $reported = null;

            try {
                $plans->apply($account, $resumed->ref, $resumed->digest);
            } catch (RuntimeException $error) {
                $reported = $error;
            }
            $done = $plans->inspect($account, $resumed->ref);
            expect($done->phase)->toBe(LifecyclePhase::Done)
                ->and(resolve(LocalWorkTransactions::class)->localTicket($account, $database))->toBeNull()
                ->and($reported)->toBe($listenerFailure)
                ->and($notifications)->toBe(1)
                ->and($done->transitionResult)->toBe(ModuleState::Purged->value)
                ->and($done->blockers)->toBe([])
                ->and($releaseAckLost)->toBeTrue()
                ->and($observer->table($table)->where('value', $plan->ref->id)->count())->toBe(1);
            expect($resumed)->toEqual($plan)
                ->and($done->phase)->toBe(LifecyclePhase::Done)->and($done->receipts)->toHaveCount(1)
                ->and($done->receipts[0]->action)->toEqual($receiptOwner->action)
                ->and($done->receipts[0]->evidence)->toBe($originalEvidence)
                ->and($plans->apply($account, $plan->ref, $plan->digest)->receipts)->toEqual($done->receipts)
                ->and($observer->table($table)->where('value', $plan->ref->id)->count())->toBe(0)
                ->and($notifications)->toBe(1);
            expect(Schema::hasTable('pmi_records'))->toBeFalse()
                ->and($lifecycle->status('fixture/packman-integration')->state)->toBe(ModuleState::Purged);

            $lifecycle->repair($name);
            expect($lifecycle->status($name)->state)->toBe(ModuleState::Available);
            $lifecycle->enable($name);
            expect($lifecycle->status($name)->state)->toBe(ModuleState::Enabled)
                ->and(Schema::hasTable('pmi_records'))->toBeTrue()
                ->and(resolve(FeatureFlags::class)->active('pmi.enabled'))->toBeTrue()
                ->and($plans->inspect($account, $plan->ref)->phase)->toBe(LifecyclePhase::Done);
            $lifecycle->enable('fixture/packman-dependent');
            expect($lifecycle->status('fixture/packman-dependent')->state)->toBe(ModuleState::Enabled)
                ->and(fn () => $lifecycle->disable($name, DisableReason::Manual, cascade: true))->toThrow(AuthorizationException::class);
            $grantDependent = true;
            $this->app->instance(ReferenceOwners::class, new class implements ReferenceOwners
            {
                public function resolve(DataScope $scope, string $producer, string $kind, array $witness): array
                {
                    if ($kind === 'sys_feature_flags' && ($witness['module'] ?? null) === 'fixture/packman-integration') {
                        return [new ModuleRef('fixture/packman-integration')];
                    }

                    throw new LogicException('Unknown fixture reference.');
                }
            });
            $lifecycle->disable($name, DisableReason::Manual, cascade: true);
            expect($lifecycle->status($name)->state)->toBe(ModuleState::Disabled)
                ->and($lifecycle->status('fixture/packman-dependent')->state)->toBe(ModuleState::Disabled)
                ->and($control->moduleClosure($account, new ModuleRef('fixture/packman-dependent')))->toBe($control->moduleClosure($account, new ModuleRef($name)));
        } finally {
            $external->statement('drop table '.$table);
        }
    } finally {
        CoreXLateIntegrationModule::$requiresRoot = false;
        $this->app->setBasePath($original);
        $files->deleteDirectory($temporary);
    }
});

it('serializes real reference writers across outer commits, rollbacks, process death and stale closures', function (): void {
    $childSchema = getenv('COREX_LIFECYCLE_CRASH_SCHEMA');
    $schema = is_string($childSchema) ? $childSchema : 'lifecycle_'.bin2hex(random_bytes(8));

    if (! preg_match('/^lifecycle_[a-f0-9]{16}$/D', $schema)) {
        throw new RuntimeException('Unexpected lifecycle test schema.');
    }
    $business = getenv('PGSQL_DATABASE');
    $controlName = getenv('COREX_LIFECYCLE_CONTROL_DATABASE') ?: preg_replace('/_test$/', '_control_test', (string) $business);

    if (! is_string($business) || ! str_ends_with($business, '_test') || ! is_string($controlName)
        || ! str_ends_with($controlName, '_test') || $business === $controlName || ! app()->environment('testing')) {
        throw new RuntimeException('Two independent explicit PostgreSQL test databases are required.');
    }
    config(['database.default' => 'pgsql', 'database.connections.lifecycle-control' => [
        ...config('database.connections.pgsql'), 'database' => $controlName,
    ]]);
    $db = DB::connection('pgsql');
    $independent = DB::connection('lifecycle-control');

    if ($db->scalar('select current_database()') !== $business || $independent->scalar('select current_database()') !== $controlName) {
        throw new RuntimeException('Unexpected physical test databases.');
    }

    if ($childSchema === false) {
        $independent->statement('CREATE SCHEMA '.$schema);
        $db->statement('CREATE SCHEMA '.$schema);
    }
    $db->statement('SET search_path TO '.$schema);
    $independent->statement('SET search_path TO '.$schema);

    if ($childSchema === false) {
        ScopeControlSchema::install($independent);
        $db->statement('CREATE TABLE lifecycle_witness (id text PRIMARY KEY)');
    }
    $contexts = LocalWorkContexts::install($this->app);
    $host = WorkScopeFixture::install($this->app, principalId: 'operator');
    config(['corex.scope.control_connection' => 'lifecycle-control']);
    $this->app->forgetInstance(ScopeOptions::class);
    $this->app->instance(ControlStoreIsolation::class, new readonly class($independent) implements ControlStoreIsolation
    {
        public function __construct(private Connection $control) {}

        public function verify(ConnectionInterface $control, string $deploymentId): void
        {
            if ($this->control !== $control || $deploymentId !== 'test-installation') {
                throw new RuntimeException('Wrong control namespace.');
            }
        }
    });
    $crashCoordinator = getenv('COREX_LIFECYCLE_COORDINATOR_CRASH') === '1';
    $contexts->initialize(new AccountRef($childSchema === false ? 'owner' : ($crashCoordinator ? 'coordinator' : 'crash'), 'owner', 'active', [], []));
    $scope = $host->scope();
    $target = new ModuleRef('axiomasoft/corex');
    $admission = resolve(ReferenceAdmission::class);
    $control = resolve(DatabaseScopeControl::class);

    if ($childSchema !== false) {
        if ($crashCoordinator) {
            $control->withLifecycleLock($scope, static function (): never {
                posix_kill(getmypid(), SIGKILL);

                throw new RuntimeException('Expected coordinator death.');
            });
        }
        $db->beginTransaction();
        $admission->enlist($scope, $db, $target);
        $db->table('lifecycle_witness')->insert(['id' => 'killed']);
        $control->fenceModule($scope, $target, 'dead-process', null);
        posix_kill(getmypid(), SIGKILL);

        throw new RuntimeException('Expected worker process death.');
    }

    try {
        $db->beginTransaction();
        $db->transaction(function () use ($admission, $db, $target, $scope): void {
            $admission->enlist($scope, $db, $target);
            $db->table('lifecycle_witness')->insert(['id' => 'before']);
        });
        $control->fenceModule($scope, $target, 'first-generation', null);
        expect($control->outstandingReferences($scope, $target))->toHaveCount(1);
        $db->commit();
        expect($control->outstandingReferences($scope, $target))->toBe([])
            ->and($db->table('lifecycle_witness')->pluck('id')->all())->toBe(['before'])
            ->and(fn () => $db->transaction(fn () => $admission->enlist($scope, $db, $target)))->toThrow(LifecycleBlocked::class);
        $control->reopenModule($scope, $target, 'first-generation');
        $db->beginTransaction();
        $admission->enlist($scope, $db, $target);
        $db->table('lifecycle_witness')->insert(['id' => 'rolled-back']);
        $control->fenceModule($scope, $target, 'second-generation', null);
        $db->rollBack();
        expect($control->outstandingReferences($scope, $target))->toBe([])
            ->and(fn () => $control->reopenModule($scope, $target, 'first-generation'))->toThrow(LifecycleBlocked::class)
            ->and($control->moduleClosure($scope, $target))->toBe('second-generation');
        $child = new Process([PHP_BINARY, 'vendor/bin/pest', __FILE__, '--filter', 'serializes real reference writers']);
        $child->setWorkingDirectory(dirname(__DIR__, 5));
        $child->setEnv(['COREX_LIFECYCLE_CRASH_SCHEMA' => $schema]);

        try {
            $child->run();
        } catch (ProcessSignaledException) {
        }
        expect($child->hasBeenSignaled())->toBeTrue($child->getErrorOutput().$child->getOutput())
            ->and($child->getTermSignal())->toBe(SIGKILL)
            ->and($db->table('lifecycle_witness')->pluck('id')->all())->toBe(['before'])
            ->and($independent->table('corex_scope_tickets')->where('account_id', 'crash')->where('state', 'outstanding')->count())->toBe(1)
            ->and($independent->table('corex_scope_routes')->where('account_id', 'crash')->value('module_closures'))->toContain('dead-process');
        $coordinator = new Process([PHP_BINARY, 'vendor/bin/pest', __FILE__, '--filter', 'serializes real reference writers']);
        $coordinator->setWorkingDirectory(dirname(__DIR__, 5));
        $coordinator->setEnv(['COREX_LIFECYCLE_CRASH_SCHEMA' => $schema, 'COREX_LIFECYCLE_COORDINATOR_CRASH' => '1']);

        try {
            $coordinator->run();
        } catch (ProcessSignaledException) {
        }
        expect($coordinator->hasBeenSignaled())->toBeTrue($coordinator->getOutput())
            ->and($coordinator->getTermSignal())->toBe(SIGKILL);
        $tickets = $independent->table('corex_scope_tickets')->where('account_id', 'coordinator');
        $record = (clone $tickets)->sole();
        expect($record->state)->toBe('outstanding');
        $interrupted = resolve(DataScopeCodec::class)->decode(json_decode($record->scope, true, flags: JSON_THROW_ON_ERROR));
        $control->withLifecycleLock($interrupted, static fn (Closure $verify) => $verify());
        expect((clone $tickets)->where('state', 'reconciled')->count())->toBe(2)
            ->and((clone $tickets)->where('evidence', 'previous-lifecycle-session-ended')->count())->toBe(1)
            ->and($independent->table('corex_scope_tickets')->where('account_id', 'crash')->where('state', 'outstanding')->count())->toBe(1);
    } finally {
        $db->statement('RESET search_path');
        $independent->statement('RESET search_path');
        config(['database.default' => 'testbench']);
        $db->statement('DROP SCHEMA '.$schema.' CASCADE');
        $independent->statement('DROP SCHEMA '.$schema.' CASCADE');
    }
});
