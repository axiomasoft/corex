<?php

declare(strict_types=1);

namespace CoreX\Operations\Laravel;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use CoreX\Context\Contracts\TenancyManager;
use CoreX\Context\Contracts\TenantContextResolver;
use CoreX\Operations\Bulk\Laravel\BulkHttpRegistrar;
use CoreX\Operations\Invocation\Contracts\ContextResolver;
use CoreX\Operations\Invocation\Internal\TrustedContextResolver;
use CoreX\Operations\Laravel\Support\ApiOptions;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Override;
use Packman\Foundation\Contracts\PackageRegistrar;

/** Composition of the operations package. */
#[Spi(kind: SpiKind::Call, since: '1.0')]
final class HttpRegistrar implements PackageRegistrar
{
    #[Override]
    public function register(Application $app): void
    {
        if (! $app->bound(ContextResolver::class)) {
            $app->bind(ContextResolver::class, static fn (Application $app): TrustedContextResolver => new TrustedContextResolver(
                tenants: $app->make(TenantContextResolver::class),
                manager: $app->make(TenancyManager::class),
                requirePhysicalTenancy: $app->make(ApiOptions::class)->requirePhysicalTenancy,
            ));
        }
    }

    #[Override]
    public function boot(Application $app): void
    {
        $options = $app->make(ApiOptions::class);

        if ($options->routesEnabled && ! $app->make('router')->has('corex.api.discovery')) {
            (new BulkHttpRegistrar)->boot($app);
            Route::prefix($options->routePrefix)
                ->middleware($options->routeMiddleware)
                ->group(__DIR__.'/../../routes/api.php');
        }
    }
}
