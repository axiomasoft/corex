<?php

declare(strict_types=1);

namespace CoreX\Panels;

use CoreX\Modules\Contracts\EntityRegistry;
use CoreX\Modules\Contracts\ModuleRegistry;
use CoreX\Panels\Contracts\PanelCatalogue;
use CoreX\Panels\Internal\EntityViewSchemaProvider;
use CoreX\Panels\Internal\ModulePanelCatalogue;
use CoreX\Panels\Internal\SavedViewsRegistrar;
use CoreX\Panels\SavedViews\Contracts\ViewSchemaProvider;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use Override;

final class PanelsServiceProvider extends ServiceProvider
{
    #[Override]
    public function register(): void
    {
        SavedViewsRegistrar::register($this->app);
        $this->app->booted(function (): void {
            $this->publishes([__DIR__.'/../database/migrations/pg' => database_path('migrations')], ['corex-migrations', 'corex-migrations-tenant']);
        });
        $this->mergeConfigFrom(__DIR__.'/../config/corex-panels.php', 'corex-panels');
        $this->app->singleton(PanelCatalogue::class, static fn (Application $app): ModulePanelCatalogue => new ModulePanelCatalogue(
            providers: $app->tagged((string) $app['config']->get('corex-panels.provider_tag')),
            entities: $app->make(EntityRegistry::class),
            modules: $app->make(ModuleRegistry::class),
        ));
        $this->app->singleton(ViewSchemaProvider::class, static fn (Application $app): EntityViewSchemaProvider => new EntityViewSchemaProvider(
            entities: $app->make(EntityRegistry::class),
        ));
    }
}
