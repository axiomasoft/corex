<?php

declare(strict_types=1);

namespace CoreX\SearchIntl;

use CoreX\SearchIntl\Contracts\SavedViewLabelReader;
use CoreX\SearchIntl\Internal\LocalizedFieldProjector;
use CoreX\SearchIntl\Internal\SqlSavedViewLabelReader;
use Illuminate\Support\ServiceProvider;
use Override;

final class SearchIntlServiceProvider extends ServiceProvider
{
    #[Override]
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/corex-intl-search.php', 'corex-intl-search');

        if ($this->app['config']->get('corex-intl-search.enabled') !== true) {
            return;
        }

        $this->app->scoped(LocalizedFieldProjector::class);
        $this->app->scoped(SavedViewLabelReader::class, SqlSavedViewLabelReader::class);
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/corex-intl-search.php' => config_path('corex-intl-search.php'),
        ], 'corex-intl-search-config');
    }
}
