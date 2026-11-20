<?php

declare(strict_types=1);

namespace App\Providers;

use App\Scenario\SearchScenarioAccess;
use App\Scenario\SearchScenarioBroker;
use App\Scenario\SearchScenarioCandidates;
use App\Scenario\SearchScenarioCredentials;
use App\Scenario\SearchScenarioEntityActivity;
use App\Scenario\SearchScenarioIndexDriver;
use App\Scenario\SearchScenarioPg;
use App\Scenario\SearchScenarioProjection;
use App\Scenario\SearchScenarioRecord;
use App\Scenario\SearchScenarioTrace;
use CoreX\Access\Matrix\Contracts\EntityActivity;
use CoreX\Context\Contracts\TenantContextResolver;
use CoreX\Data\Contracts\DataScopeAuthorizer;
use CoreX\Data\Contracts\FieldUsePolicy;
use CoreX\Messaging\Contracts\BrokerDeliverySource;
use CoreX\Messaging\Contracts\MessageContractRegistry;
use CoreX\Messaging\Data\MessageContract;
use CoreX\Messaging\Enums\MessageClass;
use CoreX\Messaging\Enums\PiiClass;
use CoreX\Messaging\Testing\MessagingFixtures;
use CoreX\Search\Index\Contracts\IndexDriver;
use CoreX\Search\Postgres\Contracts\PostgresCorpus;
use CoreX\Search\Postgres\Contracts\PostgresSnapshotAuthority;
use CoreX\Search\Projection\Contracts\SearchProjectionContributor;
use CoreX\Search\Projection\Contracts\SearchSource;
use CoreX\Search\Query\Contracts\CandidateDriver;
use CoreX\SearchMeilisearch\Contracts\MeilisearchCredentialProvider;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\ServiceProvider;
use Packman\Modules\Contracts\Entities\EntityRegistry;
use Packman\Modules\Data\Entities\EntityDefinition;
use Packman\Modules\Enums\Entities\Capability;
use Packman\Modules\Testing\ArrayEntityRegistry;

final class SearchScenarioServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $rights = getenv('COREX_SCENARIO_CASE') === 'search-rights';
        $driver = getenv('COREX_SCENARIO_SEARCH_DRIVER') === 'postgres' ? 'postgres' : 'meilisearch';
        $this->app['config']->set([
            'corex-search.driver' => $driver,
            'corex-search.cluster' => getenv('COREX_SCENARIO_MEILI_NAMESPACE'),
            'corex-search-meilisearch.host' => getenv('MEILISEARCH_HOST'),
        ]);
        $this->app->bind(MeilisearchCredentialProvider::class, SearchScenarioCredentials::class);
        $this->app->singleton(SearchScenarioTrace::class);
        $this->app->bind(SearchSource::class, SearchScenarioProjection::class);
        $this->app->tag([SearchScenarioProjection::class], SearchProjectionContributor::class);
        $this->app->bind(PostgresCorpus::class, SearchScenarioPg::class);
        $this->app->bind(PostgresSnapshotAuthority::class, SearchScenarioPg::class);
        $this->app->bind(BrokerDeliverySource::class, SearchScenarioBroker::class);
        $this->app->singleton(MessageContractRegistry::class, static fn () => MessagingFixtures::contracts([
            new MessageContract('scenario.search.changed', 2, MessageClass::IntegrationEvent, 'fixture/scenario', PiiClass::None, 'scenario-search'),
        ]));

        if ($driver === 'meilisearch') {
            $this->app->extend(CandidateDriver::class, static fn (CandidateDriver $driver, $app): CandidateDriver => new SearchScenarioCandidates(
                $driver, $app->make(SearchScenarioTrace::class),
            ));
            $this->app->extend(IndexDriver::class, static fn (IndexDriver $driver, $app): IndexDriver => new SearchScenarioIndexDriver(
                $driver, $app->make(SearchScenarioTrace::class),
            ));
        }

        if ($rights) {
            $this->app->extend(DataScopeAuthorizer::class, static fn (DataScopeAuthorizer&FieldUsePolicy $policy): SearchScenarioAccess => new SearchScenarioAccess($policy));
            $this->app->extend(EntityActivity::class, static fn (EntityActivity $installed, $app): EntityActivity => new SearchScenarioEntityActivity(
                $installed, $app->make(EntityRegistry::class), $app->make(TenantContextResolver::class), $app->make(DatabaseManager::class),
            ));
            $this->app->singleton(EntityRegistry::class, static function (): EntityRegistry {
                $registry = new ArrayEntityRegistry;
                $registry->register(new EntityDefinition(
                    handle: SearchScenarioRecord::ENTITY, model: SearchScenarioRecord::class, table: 'scenario_search_records', module: 'scenario/installed-search',
                    labelSingular: 'Search record', labelPlural: 'Search records', capabilities: [Capability::Ownable], fields: [], titleAttribute: null,
                ));

                return $registry;
            });
        } else {
            $this->app->scoped(SearchScenarioAccess::class);
            $this->app->alias(SearchScenarioAccess::class, DataScopeAuthorizer::class);
            $this->app->alias(SearchScenarioAccess::class, FieldUsePolicy::class);
        }
    }
}
