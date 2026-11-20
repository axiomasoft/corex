<?php

declare(strict_types=1);

namespace CoreX\SearchMeilisearch\Laravel;

use Closure;
use CoreX\Context\Contracts\CurrentWorkScope;
use CoreX\Search\Index\Contracts\IndexDriver;
use CoreX\Search\Index\Contracts\IndexLookupAccess;
use CoreX\Search\Index\Data\IndexTarget;
use CoreX\Search\Query\Contracts\CandidateDriver;
use CoreX\SearchMeilisearch\Contracts\MeilisearchCredentialProvider;
use CoreX\SearchMeilisearch\Enums\MeilisearchAccess;
use CoreX\SearchMeilisearch\Exceptions\InvalidMeilisearchConfiguration;
use CoreX\SearchMeilisearch\Internal\MeilisearchCandidateDriver;
use CoreX\SearchMeilisearch\Internal\MeilisearchIndexAdapter;
use CoreX\SearchMeilisearch\Internal\MeilisearchOptions;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Foundation\Application;
use Meilisearch\Client;
use Packman\Attributes\Spi;
use Packman\Attributes\SpiKind;

/** Creates candidate and indexing adapters for the configured Meilisearch cluster. */
#[Spi(kind: SpiKind::Call, since: '2.0.0')]
final class MeilisearchDriverFactory
{
    public function candidates(Application $app): CandidateDriver
    {
        return new MeilisearchCandidateDriver($this->clients($app), $app->make(CurrentWorkScope::class), $this->cluster($app));
    }

    public function index(Application $app): IndexDriver
    {
        return new MeilisearchIndexAdapter(
            clients: $this->clients($app),
            current: $app->make(CurrentWorkScope::class),
            cluster: $this->cluster($app),
            lookupAccess: $app->make(IndexLookupAccess::class),
        );
    }

    /** @return Closure(IndexTarget, MeilisearchAccess): Client */
    private function clients(Application $app): Closure
    {
        if (! class_exists(Client::class)) {
            throw InvalidMeilisearchConfiguration::missingSdk();
        }
        $options = $app->make(MeilisearchOptions::class);
        $host = $options->host;

        if ($host === null || filter_var($host, FILTER_VALIDATE_URL) === false
            || ! in_array(parse_url($host, PHP_URL_SCHEME), ['http', 'https'], true)) {
            throw InvalidMeilisearchConfiguration::invalidHost();
        }

        if (! $app->bound(MeilisearchCredentialProvider::class)) {
            throw InvalidMeilisearchConfiguration::missingCredentialProvider();
        }
        $credentials = $app->make(MeilisearchCredentialProvider::class);

        return static function (IndexTarget $target, MeilisearchAccess $access) use ($host, $credentials): Client {
            $key = $credentials->forTarget($target, $access)->reveal();

            if ($key === '') {
                throw InvalidMeilisearchConfiguration::invalidKey();
            }

            return new Client($host, $key);
        };
    }

    private function cluster(Application $app): string
    {
        $cluster = $app->make(Repository::class)->get('corex-search.cluster', 'primary');

        return is_string($cluster) && $cluster !== '' ? $cluster : 'primary';
    }
}
