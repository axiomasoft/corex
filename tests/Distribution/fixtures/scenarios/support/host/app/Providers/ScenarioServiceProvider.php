<?php

declare(strict_types=1);

namespace App\Providers;

use App\Scenario\BoxedWorkspaces;
use App\Scenario\CloudTopology;
use App\Scenario\ExpiringWakeup;
use App\Scenario\ExternalControlStore;
use App\Scenario\PhysicalMarker;
use App\Scenario\RootScheduleService;
use App\Scenario\RootSweepEffect;
use App\Scenario\Scenario;
use App\Scenario\ScenarioAuthorization;
use App\Scenario\ScenarioClock;
use App\Scenario\ScenarioJob;
use App\Scenario\ScenarioJobLedger;
use App\Scenario\ScenarioPrincipalAuthority;
use App\Scenario\ScenarioSearchSchema;
use App\Scenario\ScenarioWakeup;
use App\Scenario\WorkspaceTaskAccess;
use CoreX\Context\Contracts\ControlStoreIsolation;
use CoreX\Context\Contracts\NamedWorkPrincipal;
use CoreX\Context\Contracts\PhysicalBindingVerifier;
use CoreX\Context\Contracts\TenancyManager;
use CoreX\Context\Contracts\TenantContextResolver;
use CoreX\Context\Contracts\WorkPrincipalAuthority;
use CoreX\Context\Contracts\WorkScopeDirectory;
use CoreX\Data\Contracts\DataScopeAuthorizer;
use CoreX\Search\Contracts\SearchProvider;
use CoreX\Search\Contracts\SearchSchema;
use CoreX\Tasks\Access\Contracts\TaskAccess;
use CoreX\Tenancy\Runtime\Contracts\AccountIsolationMapper;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Support\ServiceProvider;
use Psr\Clock\ClockInterface;
use Tenant\Contracts\Context\ContextResolver;
use Tenant\Contracts\Verification\BindingVerifier;

final class ScenarioServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $case = getenv('COREX_SCENARIO_CASE');
        $cloud = $case === 'binding-restore' || getenv('COREX_SCENARIO_VARIANT') === 'cloud';
        $config = $this->app['config'];
        foreach (['control', 'a', 'b', 'backup'] as $role) {
            $name = getenv('COREX_SCENARIO_DB_'.strtoupper($role));

            if ($name !== false) {
                $config->set('database.connections.scenario_'.$role, [
                    ...$config->get('database.connections.pgsql'), 'database' => $name,
                ]);
            }
        }
        $config->set([
            'corex.scope.control_connection' => 'scenario_control',
            'corex.scope.deployment_id' => Scenario::DEPLOYMENT,
            'corex.scope.tenant_jobs' => [ScenarioJob::class],
            'corex.scope.central_jobs' => [],
            'queue.default' => 'database',
            'queue.connections.database.connection' => 'scenario_control',
            'queue.failed.database' => 'scenario_control',
            'queue.failed.driver' => 'database-uuids',
            'cache.default' => 'array',
            'session.driver' => 'array',
            'corex-search.driver' => 'postgres',
            'corex-search.cursor_secret' => str_repeat('a', 64),
        ]);
        $this->app->singleton(ControlStoreIsolation::class, ExternalControlStore::class);
        $this->app->singleton(ScenarioJobLedger::class);
        $this->app->singleton(ScenarioPrincipalAuthority::class);
        $this->app->alias(ScenarioPrincipalAuthority::class, WorkPrincipalAuthority::class);
        $this->app->scoped(DataScopeAuthorizer::class, ScenarioAuthorization::class);
        $this->app->bind(SearchSchema::class, ScenarioSearchSchema::class);

        if ($cloud) {
            $config->set('tenant', [
                'runtime_profile' => 'laravel-pg-database', 'central_connection' => 'pgsql',
                'cache_stores' => [], 'central_cache_stores' => [],
                'facilities' => ['database' => true, 'cache' => false, 'session' => false, 'storage' => false],
                'storage_driver' => 'local',
                'shared' => [
                    'cache' => ['owner' => 'scenario:host', 'purpose' => 'scenario:cache'],
                    'session' => ['owner' => 'scenario:host', 'purpose' => 'scenario:session'],
                    'storage' => ['owner' => 'scenario:host', 'purpose' => 'scenario:storage'],
                ],
            ]);
            $config->set('corex-tenancy.central_connection', 'pgsql');
            $this->app->singleton(CloudTopology::class);
            foreach ([AccountIsolationMapper::class, ContextResolver::class, BindingVerifier::class] as $contract) {
                $this->app->alias(CloudTopology::class, $contract);
            }
        }

        if ($cloud && $case === 'worker-scope') {
            $this->app->singleton(NamedWorkPrincipal::class, RootScheduleService::class);
            $config->set('corex-scheduler.handlers', [
                'scenario.root-sweep' => ['handler' => RootSweepEffect::class, 'version' => 'root-sweep/1'],
            ]);
        }

        if ($case === 'binding-restore') {
            $config->set('corex-scheduler.handlers', [
                'scenario.wakeup' => ['handler' => ScenarioWakeup::class, 'version' => 'scenario-wakeup/1'],
            ]);
            $this->app->resolving(SearchProvider::class, static function (): void {
                $connection = app('db')->connection();
                Scenario::connection('control')->table('scenario_search_resolutions')->insert([
                    'target' => Scenario::describe($connection)['database'],
                ]);
            });
        }

        if ($case === 'workspace-sweep') {
            $handlers = ['scenario.cas' => ['handler' => ExpiringWakeup::class, 'version' => 'cas/1']];

            if (getenv('COREX_SCENARIO_HANDLER_GENERATION') !== 'retired') {
                $handlers['scenario.retired'] = ['handler' => ExpiringWakeup::class, 'version' => 'retired/1'];
            }
            $config->set('corex-recurring-tasks.wakeup.enabled', true);
            $config->set('corex-scheduler.handlers', $handlers);
            $config->set('corex-scheduler.lease_seconds', 3);
            $this->app->singleton(ScenarioClock::class);
            $this->app->alias(ScenarioClock::class, ClockInterface::class);
            $this->app->scoped(TaskAccess::class, WorkspaceTaskAccess::class);
            $this->app->singleton(BoxedWorkspaces::class);
            foreach ([WorkScopeDirectory::class, TenancyManager::class, TenantContextResolver::class] as $contract) {
                $this->app->alias(BoxedWorkspaces::class, $contract);
            }
        }
        $this->app->singleton(
            PhysicalBindingVerifier::class,
            fn ($app) => new PhysicalMarker($cloud ? $app->make(CloudTopology::class) : null),
        );
    }

    public function boot(): void
    {
        // The resident program uses Laravel's Worker directly. Attach the same
        // durable failure sink registered by the queue:work console command.
        $this->app['events']->listen(JobFailed::class, function (JobFailed $event): void {
            if ($event->connectionName === 'database') {
                $this->app['queue.failer']->log(
                    $event->connectionName,
                    $event->job->getQueue(),
                    $event->job->getRawBody(),
                    $event->exception,
                );
            }
        });
    }
}
