<?php

declare(strict_types=1);

namespace CoreX\Panels;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use CoreX\Modules\Contracts\PackageRegistrar;
use CoreX\Panels\Laravel\PanelsBindingsRegistrar;
use CoreX\Panels\SavedViews\Laravel\SavedViewsBindingsRegistrar;
use Illuminate\Support\ServiceProvider;
use Override;

#[Spi(kind: SpiKind::Call, since: '1.0')]
final class PanelsServiceProvider extends ServiceProvider
{
    /** @var list<class-string<PackageRegistrar>> */
    private const array REGISTRARS = [SavedViewsBindingsRegistrar::class, PanelsBindingsRegistrar::class];

    #[Override]
    public function register(): void
    {
        $this->app->booted($this->publishMigrations(...));
        $this->mergeConfigFrom(__DIR__.'/../config/corex-panels.php', 'corex-panels');

        foreach (self::REGISTRARS as $registrar) {
            (new $registrar)->register($this->app);
        }
    }

    public function boot(): void
    {
        foreach (self::REGISTRARS as $registrar) {
            (new $registrar)->boot($this->app);
        }
    }

    private function publishMigrations(): void
    {
        $this->publishes([__DIR__.'/../database/migrations/pg' => database_path('migrations')], ['corex-panels-migrations', 'corex-panels-migrations-tenant']);
    }
}
