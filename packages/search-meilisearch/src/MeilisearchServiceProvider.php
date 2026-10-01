<?php

declare(strict_types=1);

namespace CoreX\SearchMeilisearch;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use CoreX\Config\Contracts\ConfigReportSection;
use CoreX\Search\Contracts\SearchDriverRegistry;
use CoreX\Search\Contracts\SearchProvider;
use CoreX\Search\SearchServiceProvider;
use CoreX\SearchMeilisearch\Exceptions\InvalidMeilisearchConfiguration;
use CoreX\SearchMeilisearch\Internal\MeilisearchConfigSection;
use CoreX\SearchMeilisearch\Internal\MeilisearchSearchProvider;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use Meilisearch\Client;
use Override;

#[Spi(kind: SpiKind::Call, since: '1.0')]
final class MeilisearchServiceProvider extends ServiceProvider
{
    #[Override]
    public function register(): void
    {
        if (! $this->app->bound(SearchDriverRegistry::class)) {
            $this->app->register(SearchServiceProvider::class);
        }
        $this->app->tag([MeilisearchConfigSection::class], ConfigReportSection::class);

        $this->mergeConfigFrom(__DIR__.'/../config/corex-search-meilisearch.php', 'corex-search-meilisearch');
    }

    public function boot(): void
    {
        $this->app->make(SearchDriverRegistry::class)->register('meilisearch', function (Application $app): SearchProvider {
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

            return new MeilisearchSearchProvider(
                client: new Client($host, $key),
                indexUid: $indexUid,
            );
        });
    }
}
