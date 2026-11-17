<?php

declare(strict_types=1);

namespace CoreX\Operations\Bulk\Laravel;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use CoreX\Operations\Bulk\Data\BulkOptions;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Override;
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
        $configured = $app->make(Repository::class)->get('corex-operations.routes.prefix', 'api');
        $prefix = is_string($configured) ? $configured : 'api';

        Route::prefix($prefix)->group(self::routes(...));
    }

    private static function routes(): void
    {
        Route::post('v1/bulk-operations', [BulkRouteRegistrar::class, 'submit'])->name('corex.api.bulk.submit');
        Route::get('v1/bulk-operations/{id}', [BulkRouteRegistrar::class, 'show'])->name('corex.api.bulk.show');
        Route::post('v1/bulk-operations/{id}/cancel', [BulkRouteRegistrar::class, 'cancel'])->name('corex.api.bulk.cancel');
        Route::get('v1/bulk-operations/{id}/result', [BulkRouteRegistrar::class, 'result'])->name('corex.api.bulk.result');
        Route::get('v1/bulk-operations/{id}/failures', [BulkRouteRegistrar::class, 'failures'])->name('corex.api.bulk.failures');
    }
}
