<?php

declare(strict_types=1);

namespace CoreX\Panels;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use CoreX\Modules\Contracts\EntityRegistry;
use CoreX\Modules\Contracts\ModuleRegistry;
use CoreX\Panels\Contracts\PanelCatalogue;
use CoreX\Panels\Internal\EntityViewSchemaProvider;
use CoreX\Panels\Internal\ModulePanelCatalogue;
use CoreX\Panels\Internal\PanelsOptions;
use CoreX\Panels\SavedViews\Contracts\ViewSchemaProvider;
use CoreX\Panels\SavedViews\Laravel\SavedViewsRegistrar;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use Override;

#[Spi(kind: SpiKind::Call, since: '1.0')]
final class PanelsServiceProvider extends ServiceProvider
{
    #[Override]
    public function register(): void
    {
        SavedViewsRegistrar::register($this->app);
        $this->app->booted(function (): void {
            $this->publishes([__DIR__.'/../database/migrations/pg' => database_path('migrations')], ['corex-panels-migrations', 'corex-panels-migrations-tenant']);
        });
        $this->mergeConfigFrom(__DIR__.'/../config/corex-panels.php', 'corex-panels');
        $this->app->singleton(PanelsOptions::class, static fn (Application $app): PanelsOptions => PanelsOptions::fromArray(
            (array) $app->make(Repository::class)->get('corex-panels', []),
        ));
        $this->app->singleton(PanelCatalogue::class, static fn (Application $app): ModulePanelCatalogue => new ModulePanelCatalogue(
            providers: $app->tagged($app->make(PanelsOptions::class)->providerTag),
            entities: $app->make(EntityRegistry::class),
            modules: $app->make(ModuleRegistry::class),
        ));
        $this->app->singleton(ViewSchemaProvider::class, static fn (Application $app): EntityViewSchemaProvider => new EntityViewSchemaProvider(
            entities: $app->make(EntityRegistry::class),
        ));
    }

    public function boot(): void
    {
        $this->app->make(PanelsOptions::class);
    }
}
