<?php

declare(strict_types=1);

namespace CoreX;

use CoreX\Contracts\AuditLogger;
use CoreX\Contracts\DepartmentRepository;
use CoreX\Contracts\FeatureFlags;
use CoreX\Contracts\ModuleRegistrar;
use CoreX\Contracts\PubSub;
use CoreX\Contracts\SettingDefaultsProvider;
use CoreX\Contracts\SettingsRepository;
use CoreX\Contracts\TenantResolver;
use CoreX\Contracts\TenantService;
use CoreX\Modules\ServiceProviderModuleRegistrar;
use CoreX\PubSub\PubSubManager;
use CoreX\Support\Config;
use CoreX\Tenancy\Contracts\TenantContextResolver;
use Illuminate\Support\ServiceProvider;
use Override;

final class CoreServiceProvider extends ServiceProvider
{
    #[Override]
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/corex.php', 'corex');

        $this->app->singleton(SettingDefaultsProvider::class, Config::settingDefaultsProviderClass());
        $this->app->singleton(SettingsRepository::class, Config::settingsRepositoryClass());
        $this->app->singleton(AuditLogger::class, Config::auditLoggerClass());
        $this->app->singleton(TenantService::class, Config::tenantServiceClass());
        $this->app->singleton(TenantContextResolver::class, Config::tenantContextResolverClass());
        $this->app->singleton(ModuleRegistrar::class, ServiceProviderModuleRegistrar::class);
        $this->app->singleton(FeatureFlags::class, Config::featureFlagsClass());
        $this->app->singleton(DepartmentRepository::class, Config::departmentRepositoryClass());
        $this->app->singleton(PubSub::class, PubSubManager::class);

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
    }
}
