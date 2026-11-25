<?php

declare(strict_types=1);

namespace App\Providers;

use App\BulkTransfer\BulkDrafts;
use App\BulkTransfer\EquipmentExportSchema;
use App\BulkTransfer\EquipmentInputs;
use App\BulkTransfer\PrivateStorage;
use App\FieldSearch\EquipmentLocaleSource;
use App\FieldSearch\EquipmentSource;
use App\Scenario\BoxedWorkspaces;
use App\Scenario\FixtureBrokerReceipt;
use App\Scenario\FixtureWebhookTransport;
use App\Scenario\Scenario;
use App\Scenario\ScenarioClock;
use App\Scenario\SearchScenarioCredentials;
use App\Scenario\SearchScenarioIndexDriver;
use App\Scenario\SearchScenarioTrace;
use App\TransportProof\ClientScopes;
use App\TransportProof\EquipmentFieldsPolicy;
use App\TransportProof\HostToolExecution;
use App\TransportProof\LocalWebhookReceiver;
use App\TransportProof\MetadataAdapter;
use App\TransportProof\NotesAuthority;
use App\TransportProof\NotesManifest;
use App\TransportProof\TrustedRequest;
use App\TransportProof\WorkerAuthority;
use CoreX\Context\Contracts\CurrentWorkScope;
use CoreX\Context\Contracts\TenancyManager;
use CoreX\Context\Contracts\TenantContextResolver;
use CoreX\Context\Contracts\WorkPrincipalAuthority;
use CoreX\Context\Contracts\WorkScopeDirectory;
use CoreX\CustomFields\Schemas\Contracts\FieldMetadata;
use CoreX\Data\Contracts\DataScopeAuthorizer;
use CoreX\Data\Contracts\FieldUsePolicy;
use CoreX\Data\Data\DataScope;
use CoreX\ImportExport\Contracts\ExportSchemaWitness;
use CoreX\Intl\Locale\Contracts\LocaleResolver;
use CoreX\Intl\Locale\Data\LocalePolicy;
use CoreX\Messaging\Contracts\DurableMessagePublisher;
use CoreX\Messaging\Contracts\MessageContractRegistry;
use CoreX\Messaging\Data\MessageContract;
use CoreX\Messaging\Enums\MessageClass;
use CoreX\Messaging\Enums\PiiClass;
use CoreX\Messaging\Testing\MessagingFixtures;
use CoreX\Operations\Authorization\Contracts\ClientScopeAuthorizer;
use CoreX\Operations\Authorization\Contracts\OperationAuthorizer;
use CoreX\Operations\Authorization\Contracts\OperationFieldAuthorizer;
use CoreX\Operations\Authorization\Contracts\OperationResourceAuthorizer;
use CoreX\Operations\Authorization\Contracts\PrincipalResolver;
use CoreX\Operations\Bulk\Contracts\BulkDraftFactory;
use CoreX\Operations\Bulk\Contracts\BulkTargetResolver;
use CoreX\Search\Index\Contracts\IndexDriver;
use CoreX\Search\Projection\Contracts\SearchProjectionContributor;
use CoreX\Search\Projection\Contracts\SearchSource;
use CoreX\Search\Query\Contracts\SearchMetadata;
use CoreX\SearchIntl\Contracts\LocalizedSearchValues;
use CoreX\SearchMeilisearch\Contracts\MeilisearchCredentialProvider;
use CoreX\Storage\Contracts\TenantStorage;
use CoreX\Tools\Contracts\ToolExecutionGuard;
use CoreX\Tools\Contracts\ToolPolicy;
use CoreX\Tools\Mcp\Laravel\McpToolEndpoint;
use CoreX\Webhooks\Contracts\WebhookEgressPolicy;
use CoreX\Webhooks\Contracts\WebhookHttpTransport;
use CoreX\Webhooks\Data\WebhookDestination;
use CoreX\Webhooks\Internal\WebhookFanout;
use CoreX\Webhooks\Internal\WebhookKeyRing;
use CoreX\Webhooks\Internal\WebhookMessagePublisher;
use CoreX\Webhooks\Internal\WebhookUrlPolicy;
use Equipment\Registry\TransferBulkInputs;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Packman\Config\Data\SecretRef;
use Packman\Modules\Contracts\Definition\ModuleDiscovery;
use Psr\Clock\ClockInterface;

final class TransportProofServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $config = $this->app['config'];
        $config->set('app.debug', false);
        foreach (['budget', 'sink'] as $role) {
            $database = getenv('COREX_SCENARIO_DB_'.strtoupper($role));

            if ($database !== false) {
                $config->set('database.connections.scenario_'.$role, [
                    ...$config->get('database.connections.pgsql'), 'database' => $database,
                ]);
            }
        }
        $this->app->singleton(ScenarioClock::class);
        $this->app->alias(ScenarioClock::class, ClockInterface::class);
        $this->app->scoped(NotesAuthority::class);
        foreach ([PrincipalResolver::class, OperationAuthorizer::class, OperationFieldAuthorizer::class, OperationResourceAuthorizer::class, ToolPolicy::class] as $port) {
            $this->app->alias(NotesAuthority::class, $port);
        }
        $this->app->scoped(ClientScopeAuthorizer::class, ClientScopes::class);
        $this->app->scoped(ToolExecutionGuard::class, HostToolExecution::class);
        $this->app->scoped(BulkDraftFactory::class, BulkDrafts::class);
        $this->app->scoped(BulkTargetResolver::class, BulkDrafts::class);
        $this->app->singleton(MessageContractRegistry::class, static fn () => MessagingFixtures::contracts([
            new MessageContract('fixture.recordchanged', 1, MessageClass::IntegrationEvent, NotesManifest::OWNER, PiiClass::None, 'webhooks'),
        ]));
        $this->app->scoped(DurableMessagePublisher::class, static fn ($app) => new WebhookMessagePublisher(
            $app->make(FixtureBrokerReceipt::class), $app->make(WebhookFanout::class),
        ));
        $this->app->singleton(WebhookEgressPolicy::class, static fn (): WebhookEgressPolicy => new class implements WebhookEgressPolicy
        {
            public function allows(DataScope $scope, WebhookDestination $destination): bool
            {
                resolve(CurrentWorkScope::class)->assert($scope);

                return in_array($scope->workspaceId, [null, Scenario::W1], true) && $destination->host === FixtureWebhookTransport::HOST;
            }
        });

        if (getenv('COREX_SCENARIO_VARIANT') === 'equipment') {
            $this->registerEquipment();
        }
    }

    public function boot(): void
    {
        $this->app->extend(ModuleDiscovery::class, static fn (ModuleDiscovery $installed): NotesManifest => new NotesManifest($installed));
        $this->app->singleton(WebhookHttpTransport::class, LocalWebhookReceiver::class);
        $this->app->singleton(WebhookUrlPolicy::class, static fn () => new WebhookUrlPolicy(
            resolver: static fn (string $host): array => $host === FixtureWebhookTransport::HOST ? ['93.184.216.34'] : [],
        ));
        $this->app->singleton(WebhookKeyRing::class, static function (): WebhookKeyRing {
            $keys = new WebhookKeyRing;
            $keys->provision(FixtureWebhookTransport::KEY, new SecretRef('whsec_'.base64_encode(FixtureWebhookTransport::secret())), 0);
            $keys->confirmReceiverReadiness(FixtureWebhookTransport::KEY);

            return $keys;
        });
        Route::post('mcp', McpToolEndpoint::class)->middleware(TrustedRequest::class)->name('installed.transport.mcp');
    }

    private function registerEquipment(): void
    {
        $this->app->singleton(BoxedWorkspaces::class);
        foreach ([WorkScopeDirectory::class, TenancyManager::class, TenantContextResolver::class] as $contract) {
            $this->app->alias(BoxedWorkspaces::class, $contract);
        }
        $this->app->singleton(WorkerAuthority::class);
        $this->app->alias(WorkerAuthority::class, WorkPrincipalAuthority::class);
        $this->app->scoped(TransferBulkInputs::class, EquipmentInputs::class);
        $this->app->scoped(ExportSchemaWitness::class, EquipmentExportSchema::class);
        $this->app->scoped(TenantStorage::class, PrivateStorage::class);
        $this->app->scoped(EquipmentFieldsPolicy::class);
        $this->app->alias(EquipmentFieldsPolicy::class, DataScopeAuthorizer::class);
        $this->app->alias(EquipmentFieldsPolicy::class, FieldUsePolicy::class);
        $this->app->bind(SearchSource::class, EquipmentSource::class);
        $this->app->tag(EquipmentSource::class, SearchProjectionContributor::class);
        $this->app->bind(LocalizedSearchValues::class, EquipmentLocaleSource::class);
        $this->app->bind(MeilisearchCredentialProvider::class, SearchScenarioCredentials::class);
        $this->app->singleton(SearchScenarioTrace::class);
        $this->app->extend(IndexDriver::class, static fn (IndexDriver $driver, $app): IndexDriver => new SearchScenarioIndexDriver(
            $driver, $app->make(SearchScenarioTrace::class),
        ));
        foreach (['fields.describe' => FieldMetadata::class, 'search.describe' => SearchMetadata::class] as $operation => $owner) {
            $this->app->scoped($operation, static fn ($app): MetadataAdapter => new MetadataAdapter(
                $app->make($owner), $app->make(LocaleResolver::class), $app->make(LocalePolicy::class),
            ));
        }
        $this->app['config']->set([
            'corex-search.driver' => 'meilisearch',
            'corex-search-meilisearch.host' => getenv('MEILISEARCH_HOST'),
            'corex-search.cluster' => getenv('COREX_SCENARIO_MEILI_NAMESPACE'),
            'corex-search-intl.enabled' => true,
            'corex-intl.locale.supported_tags' => ['en', 'ru-RU', 'zh-Hant-TW'],
            'corex-intl.locale.app_tag' => 'en',
            'corex-intl.locale.explicit_fallback_tags' => ['en'],
        ]);
    }
}
