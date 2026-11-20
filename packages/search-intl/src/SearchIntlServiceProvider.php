<?php

declare(strict_types=1);

namespace CoreX\SearchIntl;

use CoreX\Search\Projection\Contracts\SearchProjectionContributor;
use CoreX\SearchIntl\Contracts\LocalizedSearchValues;
use CoreX\SearchIntl\Contracts\SavedViewLabelReader;
use CoreX\SearchIntl\Internal\LocalizedFieldProjector;
use CoreX\SearchIntl\Internal\LocalizedSearchContributor;
use CoreX\SearchIntl\Internal\SearchIntlOptions;
use CoreX\SearchIntl\Internal\SqlSavedViewLabelReader;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use Override;
use Packman\Attributes\Spi;
use Packman\Attributes\SpiKind;

#[Spi(kind: SpiKind::Call, since: '1.0')]
final class SearchIntlServiceProvider extends ServiceProvider
{
    #[Override]
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/corex-search-intl.php', 'corex-search-intl');

        $this->app->singleton(SearchIntlOptions::class, static fn (Application $app): SearchIntlOptions => SearchIntlOptions::fromArray(
            (array) $app->make(Repository::class)->get('corex-search-intl', []),
        ));

        // The bindings below exist only for an enabled package; this one-off read must not cache Options before boot.
        if (! SearchIntlOptions::fromArray((array) $this->app->make(Repository::class)->get('corex-search-intl', []))->enabled) {
            return;
        }

        $this->app->scoped(SavedViewLabelReader::class, SqlSavedViewLabelReader::class);
        $this->app->scoped(LocalizedSearchContributor::class, static fn (Application $app): LocalizedSearchContributor => new LocalizedSearchContributor(
            projector: $app->make(LocalizedFieldProjector::class),
            source: $app->bound(LocalizedSearchValues::class) ? $app->make(LocalizedSearchValues::class) : null,
        ));
        $this->app->tag(LocalizedSearchContributor::class, SearchProjectionContributor::class);
    }

    public function boot(): void
    {
        $this->app->make(SearchIntlOptions::class);

        $this->publishes([
            __DIR__.'/../config/corex-search-intl.php' => config_path('corex-search-intl.php'),
        ], 'corex-search-intl-config');
    }
}
