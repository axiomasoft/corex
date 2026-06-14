<?php

declare(strict_types=1);

namespace CoreX;

use CoreX\Contracts\AuditLogger;
use CoreX\Contracts\EntityRegistry;
use CoreX\Contracts\FeatureResolver;
use CoreX\Contracts\ModuleRegistrar;
use CoreX\Contracts\SettingsRepository;
use CoreX\Contracts\TenantResolver;
use CoreX\Contracts\TenantService;
use CoreX\Features\ConfigFeatureResolver;
use CoreX\Modules\ServiceProviderModuleRegistrar;
use CoreX\Support\Config;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\ServiceProvider;
use Override;

final class CoreServiceProvider extends ServiceProvider
{
    #[Override]
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/corex.php', 'corex');

        $this->app->singleton(EntityRegistry::class, Config::entityRegistryClass());
        $this->app->singleton(SettingsRepository::class, Config::settingsRepositoryClass());
        $this->app->singleton(AuditLogger::class, Config::auditLoggerClass());
        $this->app->singleton(TenantService::class, Config::tenantServiceClass());
        $this->app->singleton(ModuleRegistrar::class, ServiceProviderModuleRegistrar::class);
        $this->app->singleton(FeatureResolver::class, ConfigFeatureResolver::class);

        $resolver = Config::tenantResolverClass();

        if ($resolver !== null) {
            $this->app->singleton(TenantResolver::class, $resolver);
        }
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        $this->publishes([
            __DIR__.'/../config/corex.php' => config_path('corex.php'),
        ], 'corex-config');

        // The entity registry is the single source of the morph map (ADR-003).
        // Built after all module providers have booted and registered entities.
        // Additive (not enforced): registered entities get stable aliases, while
        // unregistered morph models (framework/third-party) keep working.
        $this->app->booted(function (): void {
            $map = $this->app->make(EntityRegistry::class)->morphMap();

            if ($map !== []) {
                Relation::morphMap($map);
            }
        });
    }
}
