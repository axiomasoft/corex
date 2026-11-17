<?php

declare(strict_types=1);

namespace CoreX\Webhooks\Laravel;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use CoreX\Operations\Laravel\Support\ApiOptions;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Override;
use Packman\Foundation\Contracts\PackageRegistrar;

/** Registers the webhook management routes when the API routes are enabled. */
#[Spi(kind: SpiKind::Call, since: '1.0')]
final class WebhookHttpRegistrar implements PackageRegistrar
{
    #[Override]
    public function register(Application $app): void {}

    #[Override]
    public function boot(Application $app): void
    {
        if (! $app->bound(ApiOptions::class)) {
            return;
        }

        $options = $app->make(ApiOptions::class);

        if (! $options->routesEnabled || Route::has('corex.api.webhooks.subscription.list')) {
            return;
        }

        Route::prefix($options->routePrefix)->middleware($options->routeMiddleware)->group(self::routes(...));
    }

    private static function routes(): void
    {
        Route::get('v1/webhook-subscriptions', [WebhookController::class, 'forward'])->name('corex.api.webhooks.subscription.list');
        Route::post('v1/webhook-subscriptions', [WebhookController::class, 'forward'])->name('corex.api.webhooks.subscription.create');
        Route::patch('v1/webhook-subscriptions/{id}', [WebhookController::class, 'forward'])->name('corex.api.webhooks.subscription.update');
        Route::post('v1/webhook-subscriptions/{id}/disable', [WebhookController::class, 'forward'])->name('corex.api.webhooks.subscription.disable');
        Route::get('v1/webhook-deliveries', [WebhookController::class, 'forward'])->name('corex.api.webhooks.delivery.list');
        Route::post('v1/webhook-deliveries/replay', [WebhookController::class, 'forward'])->name('corex.api.webhooks.delivery.range-replay');
        Route::get('v1/webhook-deliveries/{id}', [WebhookController::class, 'forward'])->name('corex.api.webhooks.delivery.show');
        Route::get('v1/webhook-deliveries/{id}/attempts', [WebhookController::class, 'forward'])->name('corex.api.webhooks.delivery.attempts');
        Route::post('v1/webhook-deliveries/{id}/replay', [WebhookController::class, 'forward'])->name('corex.api.webhooks.delivery.replay');
    }
}
