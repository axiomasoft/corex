<?php

declare(strict_types=1);

namespace CoreX\Search;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use CoreX\Config\Contracts\ConfigReportSection;
use CoreX\Database\Contracts\SchemaSetProvider;
use CoreX\Errors\Laravel\ProblemDefinitions;
use CoreX\Search\Contracts\SearchProvider;
use CoreX\Search\Errors\Internal\PackageProblemDefinitions;
use CoreX\Search\Internal\SearchManager;
use CoreX\Search\Internal\SearchOptions;
use CoreX\Search\Internal\SearchSchemaSets;
use CoreX\Search\Internal\Support\SearchConfigSection;
use CoreX\Search\Laravel\SearchPortBindings;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use Override;

#[Spi(kind: SpiKind::Call, since: '1.0')]
final class SearchServiceProvider extends ServiceProvider
{
    #[Override]
    public function register(): void
    {
        $this->app->tag([SearchSchemaSets::class], SchemaSetProvider::class);

        ProblemDefinitions::register($this->app, __DIR__.'/../composer.json', PackageProblemDefinitions::class);

        $this->app->tag([SearchConfigSection::class], ConfigReportSection::class);

        $this->mergeConfigFrom(__DIR__.'/../config/corex-search.php', 'corex-search');

        $this->app->singleton(SearchOptions::class, static fn (Application $app): SearchOptions => SearchOptions::fromArray(
            (array) $app->make(Repository::class)->get('corex-search', []),
        ));

        $this->app->singleton(SearchManager::class, static fn (Application $app): SearchManager => new SearchManager($app));

        SearchPortBindings::register($this->app);

        $this->app->scoped(SearchProvider::class, static fn (Application $app): SearchProvider => $app->make(SearchManager::class)->driver());
    }

    public function boot(): void
    {
        $this->app->make(SearchOptions::class);
    }
}
