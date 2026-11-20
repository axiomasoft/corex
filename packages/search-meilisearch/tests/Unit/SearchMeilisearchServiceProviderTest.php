<?php

declare(strict_types=1);

use CoreX\Context\Contracts\TenantContextResolver;
use CoreX\Context\Data\AccountRef;
use CoreX\Context\Data\TenantContext;
use CoreX\Search\Contracts\SearchProvider;
use CoreX\Search\Internal\SearchOptions;
use CoreX\Search\SearchServiceProvider;
use CoreX\SearchMeilisearch\Exceptions\InvalidMeilisearchConfiguration;
use CoreX\SearchMeilisearch\Internal\MeilisearchOptions;
use CoreX\SearchMeilisearch\Internal\MeilisearchSearchProvider;
use CoreX\SearchMeilisearch\SearchMeilisearchServiceProvider;
use CoreX\Tests\Support\CallerWorkScopeFixture;
use Orchestra\Testbench\TestCase;

final class SearchMeilisearchServiceProviderTest extends TestCase
{
    #[Override]
    protected function getPackageProviders($app): array
    {
        return [SearchServiceProvider::class, SearchMeilisearchServiceProvider::class];
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->instance(TenantContextResolver::class, new class implements TenantContextResolver
        {
            public function current(): TenantContext
            {
                return new TenantContext(new AccountRef('meili-test', 'meili-test', 'active', [], []));
            }
        });
        CallerWorkScopeFixture::bind($this->app);
    }

    public function test_meilisearch_driver_without_consumer_configuration_throws(): void
    {
        $this->app['config']->set('corex-search.driver', 'meilisearch');
        $this->app->forgetInstance(SearchOptions::class);

        expect(fn (): SearchProvider => $this->app->make(SearchProvider::class))
            ->toThrow(InvalidMeilisearchConfiguration::class);
    }

    public function test_meilisearch_driver_resolves_adapter_for_explicit_consumer_configuration(): void
    {
        $this->app['config']->set('corex-search.driver', 'meilisearch');
        $this->app->forgetInstance(SearchOptions::class);
        $this->app['config']->set('corex-search-meilisearch.host', 'http://127.0.0.1:7700');
        $this->app['config']->set('corex-search-meilisearch.index_uid', 'equipment');
        $this->app['config']->set('corex-search-meilisearch.key', 'local-test-key');
        $this->app->forgetInstance(MeilisearchOptions::class);

        expect($this->app->make(SearchProvider::class))->toBeInstanceOf(MeilisearchSearchProvider::class);
    }

    public function test_meilisearch_driver_resolves_regardless_of_provider_registration_order(): void
    {
        $app = $this->app;
        new SearchMeilisearchServiceProvider($app)->register();
        new SearchServiceProvider($app)->register();
        new SearchMeilisearchServiceProvider($app)->boot();

        $app['config']->set('corex-search.driver', 'meilisearch');
        $app->forgetInstance(SearchOptions::class);
        $app['config']->set('corex-search-meilisearch.host', 'http://127.0.0.1:7700');
        $app['config']->set('corex-search-meilisearch.index_uid', 'equipment');
        $app['config']->set('corex-search-meilisearch.key', 'local-test-key');
        $app->forgetInstance(MeilisearchOptions::class);

        expect($app->make(SearchProvider::class))->toBeInstanceOf(MeilisearchSearchProvider::class);
    }
}
