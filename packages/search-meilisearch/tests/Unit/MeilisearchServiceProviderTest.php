<?php

declare(strict_types=1);

use CoreX\Search\Contracts\SearchProvider;
use CoreX\Search\SearchServiceProvider;
use CoreX\SearchMeilisearch\Exceptions\InvalidMeilisearchConfiguration;
use CoreX\SearchMeilisearch\Internal\MeilisearchSearchProvider;
use CoreX\SearchMeilisearch\MeilisearchServiceProvider;
use Orchestra\Testbench\TestCase;

final class MeilisearchServiceProviderTest extends TestCase
{
    #[Override]
    protected function getPackageProviders($app): array
    {
        return [SearchServiceProvider::class, MeilisearchServiceProvider::class];
    }

    public function test_meilisearch_driver_without_consumer_configuration_throws(): void
    {
        $this->app['config']->set('corex-search.driver', 'meilisearch');

        expect(fn (): SearchProvider => $this->app->make(SearchProvider::class))
            ->toThrow(InvalidMeilisearchConfiguration::class);
    }

    public function test_meilisearch_driver_resolves_adapter_for_explicit_consumer_configuration(): void
    {
        $this->app['config']->set('corex-search.driver', 'meilisearch');
        $this->app['config']->set('corex-search-meilisearch.host', 'http://127.0.0.1:7700');
        $this->app['config']->set('corex-search-meilisearch.index_uid', 'equipment');
        $this->app['config']->set('corex-search-meilisearch.key', 'local-test-key');

        expect($this->app->make(SearchProvider::class))->toBeInstanceOf(MeilisearchSearchProvider::class);
    }

    public function test_meilisearch_driver_resolves_regardless_of_provider_registration_order(): void
    {
        $app = $this->app;
        (new MeilisearchServiceProvider($app))->register();
        (new SearchServiceProvider($app))->register();
        (new MeilisearchServiceProvider($app))->boot();

        $app['config']->set('corex-search.driver', 'meilisearch');
        $app['config']->set('corex-search-meilisearch.host', 'http://127.0.0.1:7700');
        $app['config']->set('corex-search-meilisearch.index_uid', 'equipment');
        $app['config']->set('corex-search-meilisearch.key', 'local-test-key');

        expect($app->make(SearchProvider::class))->toBeInstanceOf(MeilisearchSearchProvider::class);
    }
}
