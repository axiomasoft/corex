<?php

declare(strict_types=1);

namespace CoreX\SearchMeilisearch;

use CoreX\Search\Contracts\SearchDriverRegistry;
use CoreX\Search\Contracts\SearchProvider;
use CoreX\Search\SearchServiceProvider;
use CoreX\SearchMeilisearch\Errors\Internal\PackageProblemDefinitions;
use CoreX\SearchMeilisearch\Internal\MeilisearchConfigSection;
use CoreX\SearchMeilisearch\Internal\MeilisearchOptions;
use CoreX\SearchMeilisearch\Laravel\MeilisearchProviderFactory;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use Override;
use Packman\Attributes\Spi;
use Packman\Attributes\SpiKind;
use Packman\Config\Contracts\ConfigReportSectionInterface as ConfigReportSection;
use Packman\Errors\Laravel\ProblemDefinitions;

#[Spi(kind: SpiKind::Call, since: '1.0')]
final class SearchMeilisearchServiceProvider extends ServiceProvider
{
    #[Override]
    public function register(): void
    {
        ProblemDefinitions::register(app: $this->app, composerManifest: __DIR__.'/../composer.json', provider: PackageProblemDefinitions::class);

        if (! $this->app->bound(SearchDriverRegistry::class)) {
            $this->app->register(SearchServiceProvider::class);
        }
        $this->app->tag([MeilisearchConfigSection::class], ConfigReportSection::class);

        $this->mergeConfigFrom(__DIR__.'/../config/corex-search-meilisearch.php', 'corex-search-meilisearch');

        $this->app->singleton(MeilisearchOptions::class, static fn (Application $app): MeilisearchOptions => MeilisearchOptions::fromArray(
            (array) $app->make(Repository::class)->get('corex-search-meilisearch', []),
        ));
    }

    public function boot(): void
    {
        $this->app->make(MeilisearchOptions::class);

        $this->app->make(SearchDriverRegistry::class)->register('meilisearch', static fn (Application $app): SearchProvider => (new MeilisearchProviderFactory)->make($app));
    }
}
