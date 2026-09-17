<?php

declare(strict_types=1);

namespace CoreX\SearchMeilisearch;

use CoreX\Search\Contracts\SearchProvider;
use CoreX\SearchMeilisearch\Internal\MeilisearchQueryAdapter;
use Illuminate\Support\ServiceProvider;
use Meilisearch\Client;
use Override;

final class MeilisearchServiceProvider extends ServiceProvider
{
    #[Override]
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/corex-search-meilisearch.php', 'corex-search-meilisearch');

        $config = $this->app['config'];
        $enabled = $config->get('corex-search-meilisearch.enabled') === true;
        $host = $config->get('corex-search-meilisearch.host');
        $indexUid = $config->get('corex-search-meilisearch.index_uid');
        $key = $config->get('corex-search-meilisearch.key');

        if (! $enabled || ! is_string($host) || filter_var($host, FILTER_VALIDATE_URL) === false || ! is_string($indexUid) || ! is_string($key)) {
            return;
        }

        $this->app->scoped(SearchProvider::class, static fn (): SearchProvider => new MeilisearchQueryAdapter(
            client: new Client($host, $key),
            indexUid: $indexUid,
        ));
    }
}
