<?php

declare(strict_types=1);

namespace CoreX\Search\Laravel;

use CoreX\Search\Contracts\SearchDriverRegistry;
use CoreX\Search\Contracts\SearchProvider;
use CoreX\Search\Internal\SearchManager;
use CoreX\Search\Internal\SearchOptions;
use Illuminate\Contracts\Foundation\Application;
use Packman\Attributes\Spi;
use Packman\Attributes\SpiKind;

/** Registers the owning package's call ports with their private implementations. */
#[Spi(kind: SpiKind::Call, since: '1.0')]
final class SearchPortBindings
{
    public static function register(Application $app): void
    {
        $app->alias(SearchManager::class, SearchDriverRegistry::class);
        $app->scoped(SearchProvider::class, static fn (Application $app): SearchProvider => $app->make(SearchManager::class)->driver($app, $app->make(SearchOptions::class)));
    }
}
