<?php

declare(strict_types=1);

namespace CoreX\SearchMeilisearch\Laravel;

use CoreX\Search\Contracts\SearchProvider;
use CoreX\SearchMeilisearch\Exceptions\InvalidMeilisearchConfiguration;
use CoreX\SearchMeilisearch\Internal\MeilisearchOptions;
use CoreX\SearchMeilisearch\Internal\MeilisearchSearchProvider;
use Illuminate\Contracts\Foundation\Application;
use Meilisearch\Client;
use Packman\Attributes\Spi;
use Packman\Attributes\SpiKind;

/** Creates the "meilisearch" search driver; host, index and key are runtime requirements of the driver. */
#[Spi(kind: SpiKind::Call, since: '1.0')]
final class MeilisearchProviderFactory
{
    public function make(Application $app): SearchProvider
    {
        if (! class_exists(Client::class)) {
            throw InvalidMeilisearchConfiguration::missingSdk();
        }

        $options = $app->make(MeilisearchOptions::class);
        $host = $options->host;

        if (! is_string($host) || filter_var($host, FILTER_VALIDATE_URL) === false) {
            throw InvalidMeilisearchConfiguration::invalidHost();
        }

        $indexUid = $options->indexUid;

        if (! is_string($indexUid) || $indexUid === '') {
            throw InvalidMeilisearchConfiguration::invalidIndexUid();
        }

        $key = $options->key?->reveal();

        if (! is_string($key) || $key === '') {
            throw InvalidMeilisearchConfiguration::invalidKey();
        }

        return new MeilisearchSearchProvider(
            client: new Client($host, $key),
            indexUid: $indexUid,
        );
    }
}
