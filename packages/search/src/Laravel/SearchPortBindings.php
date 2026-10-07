<?php

declare(strict_types=1);

namespace CoreX\Search\Laravel;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use CoreX\Search\Contracts\SearchDriverRegistry;
use CoreX\Search\Contracts\SearchProvider;
use CoreX\Search\Internal\ManagerDriverRegistry;
use CoreX\Search\Internal\SearchManager;
use Illuminate\Contracts\Foundation\Application;
use RuntimeException;

/** Registers the owning package's call ports with their private implementations. */
#[Spi(kind: SpiKind::Call, since: '1.0')]
final class SearchPortBindings
{
    public static function register(Application $app): void
    {
        $app->singleton(SearchDriverRegistry::class, static fn (Application $app): SearchDriverRegistry => new ManagerDriverRegistry($app->make(SearchManager::class)));
        $app->scoped(SearchProvider::class, static fn (Application $app): SearchProvider => self::provider($app));
    }

    private static function provider(Application $app): SearchProvider
    {
        $provider = $app->make(SearchManager::class)->driver();

        if (! $provider instanceof SearchProvider) {
            throw new RuntimeException('Search driver must implement SearchProvider.');
        }

        return $provider;
    }
}
