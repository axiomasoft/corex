<?php

declare(strict_types=1);

use CoreX\Search\Contracts\SearchProvider;
use CoreX\SearchMeilisearch\Internal\MeilisearchQueryAdapter;
use CoreX\SearchMeilisearch\MeilisearchServiceProvider;
use Orchestra\Testbench\TestCase;

final class MeilisearchServiceProviderTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [MeilisearchServiceProvider::class];
    }

    public function test_it_keeps_the_adapter_disabled_without_consumer_configuration(): void
    {
        expect($this->app['config']->get('corex-search-meilisearch.enabled'))->toBeFalse()
            ->and($this->app['config']->get('corex-search-meilisearch.host'))->toBeNull();
    }

    public function test_it_binds_the_adapter_only_for_explicit_consumer_configuration(): void
    {
        $this->app['config']->set('corex-search-meilisearch.enabled', true);
        $this->app['config']->set('corex-search-meilisearch.host', 'http://127.0.0.1:7700');
        $this->app['config']->set('corex-search-meilisearch.index_uid', 'equipment');
        $this->app['config']->set('corex-search-meilisearch.key', 'local-test-key');
        (new MeilisearchServiceProvider($this->app))->register();

        expect($this->app->make(SearchProvider::class))->toBeInstanceOf(MeilisearchQueryAdapter::class);
    }
}
