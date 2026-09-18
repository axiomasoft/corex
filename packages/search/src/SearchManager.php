<?php

declare(strict_types=1);

namespace CoreX\Search;

use CoreX\Search\Contracts\SearchProvider;
use CoreX\Search\Contracts\SearchSchema;
use CoreX\Search\Exceptions\InvalidSearchConfiguration;
use CoreX\Search\Internal\Postgres\PostgresSearchProvider;
use CoreX\Search\Internal\Query\SearchCursorCodec;
use CoreX\Search\Internal\UnsupportedSearchProvider;
use Illuminate\Support\Manager;
use Override;

final class SearchManager extends Manager
{
    #[Override]
    public function getDefaultDriver(): string
    {
        $driver = $this->config->get('corex-search.driver');

        if (! is_string($driver) || $driver === '') {
            throw InvalidSearchConfiguration::missingDriver();
        }

        return $driver;
    }

    protected function createNullDriver(): SearchProvider
    {
        return new UnsupportedSearchProvider;
    }

    protected function createPostgresDriver(): SearchProvider
    {
        $secret = $this->config->get('corex-search.cursor_secret');

        if (! is_string($secret) || strlen($secret) < 32) {
            throw InvalidSearchConfiguration::weakCursorSecret();
        }

        if (! $this->container->bound(SearchSchema::class)) {
            throw InvalidSearchConfiguration::missingSchemaBinding();
        }

        if (! $this->container->bound('db')) {
            throw InvalidSearchConfiguration::missingDatabase();
        }

        return new PostgresSearchProvider(
            connection: $this->container->make('db')->connection(),
            schemas: $this->container->make(SearchSchema::class),
            cursors: new SearchCursorCodec(secret: $secret),
            configuration: (string) $this->config->get('corex-search.postgres.text_search_config', 'simple'),
        );
    }
}
