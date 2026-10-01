<?php

declare(strict_types=1);

namespace CoreX\SearchIntl;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use CoreX\SearchIntl\Contracts\SavedViewLabelReader;
use CoreX\SearchIntl\Internal\LocalizedFieldProjector;
use CoreX\SearchIntl\Internal\SqlSavedViewLabelReader;
use Illuminate\Support\ServiceProvider;
use Override;

#[Spi(kind: SpiKind::Call, since: '1.0')]
final class SearchIntlServiceProvider extends ServiceProvider
{
    #[Override]
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/corex-search-intl.php', 'corex-search-intl');

        if ($this->app['config']->get('corex-search-intl.enabled') !== true) {
            return;
        }

        $this->app->scoped(LocalizedFieldProjector::class);
        $this->app->scoped(SavedViewLabelReader::class, SqlSavedViewLabelReader::class);
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/corex-search-intl.php' => config_path('corex-search-intl.php'),
        ], 'corex-search-intl-config');
    }
}
