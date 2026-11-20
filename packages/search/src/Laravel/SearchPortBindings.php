<?php

declare(strict_types=1);

namespace CoreX\Search\Laravel;

use CoreX\Context\Contracts\CurrentWorkScope;
use CoreX\Context\Contracts\DataScopeFactory;
use CoreX\Context\Contracts\TenantContextResolver;
use CoreX\Context\Contracts\WorkScopeRunner;
use CoreX\Data\Contracts\DataScopeAuthorizer;
use CoreX\Data\Contracts\FieldUsePolicy;
use CoreX\Data\Data\DataScope;
use CoreX\Search\Errors\Exceptions\SearchFeatureUnavailable;
use CoreX\Search\Index\Contracts\ActiveGenerations;
use CoreX\Search\Index\Contracts\IndexDriver;
use CoreX\Search\Internal\Postgres\PostgresCandidateDriver;
use CoreX\Search\Internal\SearchManager;
use CoreX\Search\Internal\SearchOptions;
use CoreX\Search\Internal\UnavailableSearchProvider;
use CoreX\Search\Postgres\Contracts\PostgresCorpus;
use CoreX\Search\Postgres\Contracts\PostgresSnapshotAuthority;
use CoreX\Search\Projection\Contracts\SearchProjectionContributor;
use CoreX\Search\Projection\Contracts\SearchSource;
use CoreX\Search\Projection\Data\ProjectionContext;
use CoreX\Search\Projection\Internal\CompositeProjection;
use CoreX\Search\Query\Contracts\CandidateDriver;
use CoreX\Search\Query\Contracts\LiveCandidateDriver;
use CoreX\Search\Query\Contracts\Search;
use CoreX\Search\Query\Contracts\SearchDriverRegistry;
use CoreX\Search\Query\Data\SearchQuery;
use CoreX\Search\Query\Internal\AuthorizedHydrator;
use CoreX\Search\Query\Internal\AuthorizedSearch;
use CoreX\Search\Query\Internal\QuerySession;
use CoreX\Search\Query\Internal\SearchCursor;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\DatabaseManager;
use LogicException;
use Packman\Attributes\Spi;
use Packman\Attributes\SpiKind;

#[Spi(kind: SpiKind::Call, since: '2.0.0')]
final class SearchPortBindings
{
    public static function register(Application $app): void
    {
        $app->alias(SearchManager::class, SearchDriverRegistry::class);
        $manager = $app->make(SearchManager::class);
        $manager->register('null', static fn (): CandidateDriver => new UnavailableSearchProvider);
        $manager->register('postgres', static function (Application $app): CandidateDriver {
            $options = $app->make(SearchOptions::class);

            return new PostgresCandidateDriver(connection: $app->make(DatabaseManager::class)->connection(),
                current: $app->make(CurrentWorkScope::class), corpus: $app->make(PostgresCorpus::class),
                configuration: $options->textSearchConfig, cluster: $options->cluster,
                authority: $app->bound(PostgresSnapshotAuthority::class) ? $app->make(PostgresSnapshotAuthority::class) : null);
        });
        $app->bind(CandidateDriver::class, static function (Application $app): CandidateDriver {
            $name = $app->make(SearchOptions::class)->driver;

            if ($name !== 'null') {
                self::assertCurrent($app);
            }

            return $app->make(SearchManager::class)->candidate($app, $name);
        });
        // Submit and receipt recovery verify their respective authority at the operation boundary.
        $app->bind(IndexDriver::class, static fn (Application $app): IndexDriver => $app->make(SearchManager::class)->index($app, $app->make(SearchOptions::class)->driver));
        $app->bind(SearchProjectionContributor::class, static function (Application $app): SearchProjectionContributor {
            $parts = [];
            foreach ($app->tagged(SearchProjectionContributor::class) as $part) {
                if (! $part instanceof SearchProjectionContributor) {
                    throw new LogicException('Invalid tagged search projection contributor.');
                }
                $parts[] = $part;
            }

            return new CompositeProjection($parts);
        });
        $app->bind(Search::class, static fn (Application $app): Search => new AuthorizedSearch(
            $app->make(WorkScopeRunner::class), $app->make(CurrentWorkScope::class),
            static fn (DataScope $scope, SearchQuery $query): QuerySession => self::session($app, $scope, $query),
        ));
    }

    private static function session(Application $app, DataScope $scope, SearchQuery $query): QuerySession
    {
        $options = $app->make(SearchOptions::class);

        if ($options->driver === 'null') {
            throw SearchFeatureUnavailable::unsupported();
        }
        $cursor = new SearchCursor($options->cursorSecret?->reveal() ?? '');
        $driver = self::selected($app);
        $projection = $app->make(SearchProjectionContributor::class);

        return new QuerySession(
            context: new ProjectionContext($scope, $query->entity), query: $query, driver: $driver,
            policy: $app->make(FieldUsePolicy::class), projection: $projection,
            hydrator: new AuthorizedHydrator($app->make(SearchSource::class), $projection, $app->make(DataScopeAuthorizer::class)),
            current: $app->make(CurrentWorkScope::class), generations: $driver instanceof LiveCandidateDriver ? null : $app->make(ActiveGenerations::class),
            cursor: $cursor, backend: $options->driver, cluster: $options->cluster, budget: $options->scanBudget,
        );
    }

    private static function selected(Application $app): CandidateDriver
    {
        return $app->make(CandidateDriver::class);
    }

    private static function assertCurrent(Application $app): void
    {
        $context = $app->make(TenantContextResolver::class)->current();
        $scope = $app->make(DataScopeFactory::class)->forCurrentPrincipal($context);
        $app->make(CurrentWorkScope::class)->assert($scope);
    }
}
