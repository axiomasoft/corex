<?php

declare(strict_types=1);

namespace CoreX\Search\Meilisearch;

use CoreX\Search\Contracts\SearchProvider;
use CoreX\Search\Meilisearch\Exceptions\InvalidMeilisearchConfiguration;
use CoreX\Search\Meilisearch\Internal\MeilisearchQueryAdapter;
use CoreX\Search\SearchManager;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use Meilisearch\Client;
use Override;

final class MeilisearchServiceProvider extends ServiceProvider
{
    #[Override]
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/corex-search-meilisearch.php', 'corex-search-meilisearch');
    }

    public function boot(): void
    {
        $this->app->make(SearchManager::class)->extend('meilisearch', function (Application $app): SearchProvider {
            if (! class_exists(Client::class)) {
                throw InvalidMeilisearchConfiguration::missingSdk();
            }

            $host = $app['config']->get('corex-search-meilisearch.host');

            if (! is_string($host) || filter_var($host, FILTER_VALIDATE_URL) === false) {
                throw InvalidMeilisearchConfiguration::invalidHost();
            }

            $indexUid = $app['config']->get('corex-search-meilisearch.index_uid');

            if (! is_string($indexUid) || $indexUid === '') {
                throw InvalidMeilisearchConfiguration::invalidIndexUid();
            }

            $key = $app['config']->get('corex-search-meilisearch.key');

            if (! is_string($key) || $key === '') {
                throw InvalidMeilisearchConfiguration::invalidKey();
            }

            return new MeilisearchQueryAdapter(
                client: new Client($host, $key),
                indexUid: $indexUid,
            );
        });
    }
}
