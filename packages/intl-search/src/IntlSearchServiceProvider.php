<?php

declare(strict_types=1);

namespace CoreX\IntlSearch;

use CoreX\IntlSearch\Contracts\SavedViewLabelReader;
use CoreX\IntlSearch\Internal\LocalizedFieldProjector;
use CoreX\IntlSearch\Internal\SqlSavedViewLabelReader;
use Illuminate\Support\ServiceProvider;
use Override;

final class IntlSearchServiceProvider extends ServiceProvider
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
