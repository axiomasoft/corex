<?php

declare(strict_types=1);

namespace CoreX\Search;

use CoreX\Search\Contracts\SearchProvider;
use CoreX\Search\Contracts\SearchSchema;
use CoreX\Search\Internal\Postgres\PostgresSearchProvider;
use CoreX\Search\Internal\Query\SearchCursorCodec;
use CoreX\Search\Internal\UnsupportedSearchProvider;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use Override;

final class SearchServiceProvider extends ServiceProvider
{
    #[Override]
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/corex-search.php', 'corex-search');

        $this->app->scoped(SearchProvider::class, function (Application $app): SearchProvider {
            $cursorSecret = $app['config']->get('corex-search.cursor_secret');

            if ($app['config']->get('corex-search.driver') !== 'postgres' || ! is_string($cursorSecret) || strlen($cursorSecret) < 32 || ! $app->bound(SearchSchema::class) || ! $app->bound('db')) {
                return new UnsupportedSearchProvider;
            }

            return new PostgresSearchProvider(
                connection: $app['db']->connection(),
                schemas: $app->make(SearchSchema::class),
                cursors: new SearchCursorCodec(secret: $cursorSecret),
                configuration: (string) $app['config']->get('corex-search.postgres.text_search_config', 'simple'),
            );
        });
    }
}
