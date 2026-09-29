<?php

declare(strict_types=1);

namespace CoreX\Search;

use CoreX\Config\Contracts\ConfigReportSection;
use CoreX\Search\Contracts\SearchProvider;
use CoreX\Search\Internal\Support\SearchConfigSection;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use Override;

final class SearchServiceProvider extends ServiceProvider
{
    #[Override]
    public function register(): void
    {
        $this->app->tag([SearchConfigSection::class], ConfigReportSection::class);

        $this->mergeConfigFrom(__DIR__.'/../config/corex-search.php', 'corex-search');

        $this->app->singleton(SearchManager::class, static fn (Application $app): SearchManager => new SearchManager($app));

        $this->app->scoped(SearchProvider::class, static fn (Application $app): SearchProvider => $app->make(SearchManager::class)->driver());
    }
}
