<?php

declare(strict_types=1);

namespace CoreX\Operations\Bulk\Laravel;

use CoreX\Operations\Bulk\Data\BulkOptions;
use CoreX\Operations\Errors\Laravel\OperationErrorBoundary;
use CoreX\Operations\Laravel\Support\ApiOptions;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Override;
use Packman\Attributes\Spi;
use Packman\Attributes\SpiKind;
use Packman\Foundation\Contracts\PackageRegistrar;

/** Registers the bulk operation routes once the API routes are enabled. */
#[Spi(kind: SpiKind::Call, since: '1.0')]
final class BulkHttpRegistrar implements PackageRegistrar
{
    #[Override]
    public function register(Application $app): void {}

    #[Override]
    public function boot(Application $app): void
    {
        if (! $app->make(BulkOptions::class)->enabled) {
            return;
        }

        $this->registerRoutes($app);
    }

    public function registerRoutes(Application $app): void
    {
        $options = $app->make(ApiOptions::class);

        if ($options->routesEnabled && ! Route::has('corex.api.bulk.submit')) {
            Route::prefix($options->routePrefix)
                ->middleware([OperationErrorBoundary::class, ...$options->routeMiddleware])
                ->group(self::routes(...));
        }
    }

    private static function routes(): void
    {
        Route::post('v2/bulk-operations', [BulkRouteRegistrar::class, 'submit'])->name('corex.api.bulk.submit');
        Route::get('v2/bulk-operations/{id}', [BulkRouteRegistrar::class, 'show'])->name('corex.api.bulk.show');
        Route::post('v2/bulk-operations/{id}/cancel', [BulkRouteRegistrar::class, 'cancel'])->name('corex.api.bulk.cancel');
        Route::get('v2/bulk-operations/{id}/result', [BulkRouteRegistrar::class, 'result'])->name('corex.api.bulk.result');
        Route::get('v2/bulk-operations/{id}/failures', [BulkRouteRegistrar::class, 'failures'])->name('corex.api.bulk.failures');
    }
}
