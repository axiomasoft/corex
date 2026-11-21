<?php

declare(strict_types=1);

namespace App\Providers;

use App\Scenario\BindingRestoreRecoveryAuthority;
use App\Scenario\BoxedWorkspaces;
use App\Scenario\CloudTopology;
use App\Scenario\ExpiringWakeup;
use App\Scenario\ExternalControlStore;
use App\Scenario\FieldBackfillBarrier;
use App\Scenario\FieldScenarioPolicy;
use App\Scenario\FieldScenarioRoute;
use App\Scenario\FixtureBrokerReceipt;
use App\Scenario\FixtureNotificationDriver;
use App\Scenario\FixtureNotificationPolicy;
use App\Scenario\FixtureProviderRecoveryAuthority;
use App\Scenario\FixtureWebhookEgress;
use App\Scenario\FixtureWebhookTransport;
use App\Scenario\FixtureWorkflowEngine;
use App\Scenario\NotificationRelated;
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
use CoreX\Context\Contracts\AccountConnectionResolver;
use CoreX\Context\Contracts\ControlStoreIsolation;
use CoreX\Context\Contracts\NamedWorkPrincipal;
use CoreX\Context\Contracts\PhysicalBindingVerifier;
use CoreX\Context\Contracts\ProviderRecoveryAuthority;
use CoreX\Context\Contracts\TenancyManager;
use CoreX\Context\Contracts\TenantContextResolver;
use CoreX\Context\Contracts\WorkPrincipalAuthority;
use CoreX\Context\Contracts\WorkScopeDirectory;
use CoreX\CustomFields\Types\Contracts\FieldConverterProvider;
use CoreX\Data\Contracts\DataScopeAuthorizer;
use CoreX\Data\Contracts\FieldUsePolicy;
use CoreX\Messaging\Contracts\DurableMessagePublisher;
use CoreX\Messaging\Contracts\MessageContractRegistry;
use CoreX\Messaging\Data\MessageContract;
use CoreX\Messaging\Enums\MessageClass;
use CoreX\Messaging\Enums\PiiClass;
use CoreX\Messaging\Testing\MessagingFixtures;
use CoreX\Notifications\Delivery\Contracts\RecipientResolver;
use CoreX\Notifications\Inbox\Contracts\NotificationPolicy;
use CoreX\Search\Postgres\Contracts\PostgresCorpus;
use CoreX\Search\Projection\Contracts\SearchProjectionContributor;
use CoreX\Search\Projection\Contracts\SearchSource;
use CoreX\Search\Query\Contracts\CandidateDriver;
use CoreX\Tasks\Access\Contracts\TaskAccess;
use CoreX\Tenancy\Runtime\Contracts\AccountIsolationMapper;
use CoreX\Webhooks\Contracts\WebhookEgressPolicy;
use CoreX\Webhooks\Contracts\WebhookHttpTransport;
use CoreX\Webhooks\Internal\WebhookFanout;
use CoreX\Webhooks\Internal\WebhookKeyRing;
use CoreX\Webhooks\Internal\WebhookMessagePublisher;
use CoreX\Webhooks\Internal\WebhookUrlPolicy;
use CoreX\Workflow\Contracts\EngineAdapter;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Support\ServiceProvider;
use Packman\Config\Data\SecretRef;
use Packman\Modules\Contracts\Entities\EntityRegistry;
use Packman\Modules\Contracts\Registry\ModuleRegistry;
use Packman\Modules\Data\Entities\EntityDefinition;
use Packman\Modules\Testing\ArrayEntityRegistry;
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

        if ($case !== 'search-rights') {
            $this->app->scoped(DataScopeAuthorizer::class, ScenarioAuthorization::class);
        }

        if (in_array($case, ['worker-scope', 'binding-restore'], true)) {
            $this->app->scoped(FieldUsePolicy::class, ScenarioAuthorization::class);
            $this->app->bind(PostgresCorpus::class, ScenarioSearchSchema::class);
            $this->app->bind(SearchSource::class, ScenarioSearchSchema::class);
            $this->app->tag(ScenarioSearchSchema::class, SearchProjectionContributor::class);
        }

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
            $this->app->singleton(ProviderRecoveryAuthority::class, BindingRestoreRecoveryAuthority::class);
            $config->set('corex-scheduler.handlers', [
                'scenario.wakeup' => ['handler' => ScenarioWakeup::class, 'version' => 'scenario-wakeup/1'],
            ]);
            $this->app->resolving(CandidateDriver::class, static function (): void {
                $connection = app('db')->connection();
                Scenario::connection('control')->table('scenario_search_resolutions')->insert([
                    'target' => Scenario::describe($connection)['database'],
                ]);
            });
        }

        if (in_array($case, ['workspace-sweep', 'notification-visibility', 'provider-unknown', 'field-write', 'ddl-claim', 'search-rights', 'generation', 'field-search'], true)) {
            $this->app->singleton(ScenarioClock::class);
            $this->app->alias(ScenarioClock::class, ClockInterface::class);
            $this->app->singleton(BoxedWorkspaces::class);
            foreach ([WorkScopeDirectory::class, TenancyManager::class, TenantContextResolver::class] as $contract) {
                $this->app->alias(BoxedWorkspaces::class, $contract);
            }
        }

        if (in_array($case, ['search-rights', 'generation', 'field-search'], true)) {
            $this->app->register(SearchScenarioServiceProvider::class);
        }

        if ($case === 'ddl-claim') {
            $this->app->scoped(AccountConnectionResolver::class, FieldScenarioRoute::class);
        }

        if (in_array($case, ['field-write', 'ddl-claim'], true)) {
            $this->app->scoped(FieldUsePolicy::class, FieldScenarioPolicy::class);
        }

        if ($case === 'field-write' && getenv('COREX_SCENARIO_PAUSE_BACKFILL') === '1') {
            $this->app->extend('ThirdParty\\Attributes\\InspectionCodeConverterProvider', static fn (FieldConverterProvider $provider): FieldBackfillBarrier => new FieldBackfillBarrier($provider));
        }

        if (in_array($case, ['notification-visibility', 'provider-unknown'], true)) {
            $config->set('queue.default', 'sync');
            $config->set('corex-notifications.delivery.claim_seconds', 3);
            $this->app->singleton(FixtureNotificationPolicy::class);
            $this->app->alias(FixtureNotificationPolicy::class, NotificationPolicy::class);
            $this->app->alias(FixtureNotificationPolicy::class, RecipientResolver::class);
            $this->app->singleton(FixtureNotificationDriver::class);
            $this->app->tag(FixtureNotificationDriver::class, 'corex.notifications.channels');
        }

        if ($case === 'notification-visibility') {
            $this->app->singleton(EntityRegistry::class, static function ($app): EntityRegistry {
                $registry = new ArrayEntityRegistry(
                    moduleActive: static fn (string $module, mixed $context): bool => $app->make(ModuleRegistry::class)->isActive($module, $context),
                );
                $registry->register(new EntityDefinition(
                    handle: NotificationRelated::HANDLE, model: NotificationRelated::class, table: 'scenario_records', module: 'axiomasoft/corex-notifications',
                    labelSingular: 'Related record', labelPlural: 'Related records', capabilities: [], fields: [], titleAttribute: null,
                ));

                return $registry;
            });
        }

        if ($case === 'provider-unknown') {
            $config->set('corex-workflow.enforce_declarations', false);
            $this->app->singleton(EngineAdapter::class, FixtureWorkflowEngine::class);
            $this->app->singleton(FixtureProviderRecoveryAuthority::class);
            $this->app->alias(FixtureProviderRecoveryAuthority::class, ProviderRecoveryAuthority::class);
            $this->app->singleton(WebhookEgressPolicy::class, FixtureWebhookEgress::class);
            $this->app->singleton(WebhookHttpTransport::class, FixtureWebhookTransport::class);
            $this->app->singleton(WebhookUrlPolicy::class, static fn () => new WebhookUrlPolicy(
                resolver: static fn (string $host): array => $host === FixtureWebhookTransport::HOST ? ['93.184.216.34'] : [],
            ));
            $this->app->singleton(WebhookKeyRing::class, static function (): WebhookKeyRing {
                $keys = new WebhookKeyRing;
                $keys->provision(FixtureWebhookTransport::KEY, new SecretRef('whsec_'.base64_encode(FixtureWebhookTransport::secret())), 0);

                return $keys;
            });
            $this->app->singleton(MessageContractRegistry::class, static fn () => MessagingFixtures::contracts([
                new MessageContract('fixture.recordchanged', 1, MessageClass::IntegrationEvent, 'fixture/scenario', PiiClass::None, 'webhooks'),
            ]));
            $this->app->scoped(DurableMessagePublisher::class, static fn ($app) => new WebhookMessagePublisher(
                $app->make(FixtureBrokerReceipt::class), $app->make(WebhookFanout::class),
            ));
        }

        if ($case === 'workspace-sweep') {
            $handlers = ['scenario.cas' => ['handler' => ExpiringWakeup::class, 'version' => 'cas/1']];

            if (getenv('COREX_SCENARIO_HANDLER_GENERATION') !== 'retired') {
                $handlers['scenario.retired'] = ['handler' => ExpiringWakeup::class, 'version' => 'retired/1'];
            }
            $config->set('corex-recurring-tasks.wakeup.enabled', true);
            $config->set('corex-scheduler.handlers', $handlers);
            $config->set('corex-scheduler.lease_seconds', 3);
            $this->app->scoped(TaskAccess::class, WorkspaceTaskAccess::class);
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
