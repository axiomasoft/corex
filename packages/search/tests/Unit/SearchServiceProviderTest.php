<?php

declare(strict_types=1);

use CoreX\Context\Contracts\CurrentWorkScope;
use CoreX\Context\Contracts\TenantContextResolver;
use CoreX\Context\Data\AccountRef;
use CoreX\Context\Data\TenantContext;
use CoreX\Context\Exceptions\ScopeContextMismatch;
use CoreX\Data\Data\DataScope;
use CoreX\Search\Contracts\SearchDriverRegistry;
use CoreX\Search\Contracts\SearchProvider;
use CoreX\Search\Contracts\SearchSchema;
use CoreX\Search\Data\SearchSnapshot;
use CoreX\Search\Exceptions\InvalidSearchConfiguration;
use CoreX\Search\Internal\Postgres\PostgresSearchProvider;
use CoreX\Search\Internal\SearchOptions;
use CoreX\Search\Internal\UnavailableSearchProvider;
use CoreX\Search\SearchServiceProvider;
use CoreX\Tests\Support\CallerWorkScopeFixture;
use Illuminate\Database\ConnectionInterface;
use Orchestra\Testbench\TestCase;

final class SearchServiceProviderTest extends TestCase
{
    #[Override]
    protected function getPackageProviders($app): array
    {
        return [SearchServiceProvider::class];
    }

    public function test_it_boots_without_database_schemas_sdk_credentials_or_network_activity(): void
    {
        expect($this->app->make(SearchProvider::class))->toBeInstanceOf(UnavailableSearchProvider::class);
    }

    public function test_null_driver_returns_unsupported_search_provider(): void
    {
        $this->app['config']->set('corex-search.driver', 'null');
        $this->app->forgetInstance(SearchOptions::class);

        expect($this->app->make(SearchProvider::class))->toBeInstanceOf(UnavailableSearchProvider::class);
    }

    public function test_postgres_driver_with_weak_cursor_secret_throws(): void
    {
        $this->app['config']->set('corex-search.driver', 'postgres');
        $this->app->forgetInstance(SearchOptions::class);
        $this->app['config']->set('corex-search.cursor_secret', 'short');

        expect(fn (): SearchProvider => $this->app->make(SearchProvider::class))
            ->toThrow(InvalidSearchConfiguration::class);
    }

    public function test_meilisearch_driver_without_adapter_throws(): void
    {
        $this->app['config']->set('corex-search.driver', 'meilisearch');
        $this->app->forgetInstance(SearchOptions::class);

        expect(fn (): SearchProvider => $this->app->make(SearchProvider::class))
            ->toThrow(InvalidArgumentException::class);
    }

    public function test_registered_factory_is_not_called_until_the_active_owner_is_proved(): void
    {
        $this->app['config']->set('corex-search.driver', 'fixture');
        $this->app->forgetInstance(SearchOptions::class);
        $this->app->instance(TenantContextResolver::class, new class implements TenantContextResolver
        {
            public function current(): TenantContext
            {
                return new TenantContext(new AccountRef('search-test', 'search-test', 'active', [], []));
            }
        });
        CallerWorkScopeFixture::bind($this->app);
        $this->app->instance(CurrentWorkScope::class, new class implements CurrentWorkScope
        {
            public function assert(DataScope $scope, ?ConnectionInterface $connection = null): TenantContext
            {
                throw new ScopeContextMismatch('Fixture physical owner changed.');
            }
        });

        $calls = 0;
        $this->app->make(SearchDriverRegistry::class)->register('fixture', static function () use (&$calls): SearchProvider {
            $calls++;

            return new UnavailableSearchProvider;
        });

        expect(fn (): SearchProvider => $this->app->make(SearchProvider::class))->toThrow(ScopeContextMismatch::class)
            ->and($calls)->toBe(0);
    }

    public function test_it_selects_the_postgresql_provider_only_for_an_explicit_ready_profile(): void
    {
        $this->app['config']->set('corex-search.driver', 'postgres');
        $this->app->forgetInstance(SearchOptions::class);
        $this->app['config']->set('corex-search.cursor_secret', str_repeat('a', 32));
        $this->app->instance(TenantContextResolver::class, new class implements TenantContextResolver
        {
            public function current(): TenantContext
            {
                return new TenantContext(new AccountRef('search-test', 'search-test', 'active', [], []));
            }
        });
        CallerWorkScopeFixture::bind($this->app);
        $this->app->instance(SearchSchema::class, new class implements SearchSchema
        {
            public function describe(DataScope $scope, string $entity): SearchSnapshot
            {
                return new SearchSnapshot(entity: $entity, schemaHash: str_repeat('b', 64), table: 'equipment', idColumn: 'id', searchableColumns: ['title']);
            }
        });

        expect($this->app->make(SearchProvider::class))->toBeInstanceOf(PostgresSearchProvider::class);
    }
}
