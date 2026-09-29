<?php

declare(strict_types=1);

namespace CoreX\Webhooks\Laravel;

use CoreX\Operations\Definition\Operation;
use CoreX\Operations\Definition\OperationRegistry;
use CoreX\Operations\Errors\ApiError;
use CoreX\Operations\Idempotency\Enums\IdempotencyRequirement;
use CoreX\Operations\Invocation\OperationDispatcher;
use CoreX\Operations\Laravel\Support\ApiOptions;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/** @internal Literal webhook routes forwarded into the shared P13 dispatcher/invoker stack. */
final readonly class WebhookRouteRegistrar
{
    public static function register(Application $app): void
    {
        if (! $app->bound(ApiOptions::class)) {
            return;
        }

        $options = $app->make(ApiOptions::class);

        if (! $options->routesEnabled || Route::has('corex.api.webhooks.subscription.list')) {
            return;
        }

        Route::prefix($options->routePrefix)->middleware($options->routeMiddleware)->group(static function (): void {
            Route::get('v1/webhook-subscriptions', [self::class, 'forward'])->name('corex.api.webhooks.subscription.list');
            Route::post('v1/webhook-subscriptions', [self::class, 'forward'])->name('corex.api.webhooks.subscription.create');
            Route::patch('v1/webhook-subscriptions/{id}', [self::class, 'forward'])->name('corex.api.webhooks.subscription.update');
            Route::post('v1/webhook-subscriptions/{id}/disable', [self::class, 'forward'])->name('corex.api.webhooks.subscription.disable');
            Route::get('v1/webhook-deliveries', [self::class, 'forward'])->name('corex.api.webhooks.delivery.list');
            Route::post('v1/webhook-deliveries/replay', [self::class, 'forward'])->name('corex.api.webhooks.delivery.range-replay');
            Route::get('v1/webhook-deliveries/{id}', [self::class, 'forward'])->name('corex.api.webhooks.delivery.show');
            Route::get('v1/webhook-deliveries/{id}/attempts', [self::class, 'forward'])->name('corex.api.webhooks.delivery.attempts');
            Route::post('v1/webhook-deliveries/{id}/replay', [self::class, 'forward'])->name('corex.api.webhooks.delivery.replay');
        });
    }

    public function forward(Request $request, OperationDispatcher $dispatcher, OperationRegistry $operations): JsonResponse
    {
        $path = ltrim($request->path(), '/');
        $marker = 'v1/';
        $offset = str_starts_with($path, $marker) ? 0 : strpos($path, '/'.$marker);

        if ($offset === false) {
            throw new ApiError('operation_not_found', 404, 'Operation not found.');
        }

        $relative = substr($path, $offset + strlen($marker) + ($offset === 0 ? 0 : 1));
        [$operation] = $operations->match('v1', $request->method(), '/'.$relative);
        self::assertOperationContract($operation);

        return $dispatcher->dispatch($request, 'v1', $relative);
    }

    public static function assertOperationContract(Operation $operation): void
    {
        $scope = str_contains($operation->path, '/replay') ? 'webhooks.replay' : 'webhooks.manage';
        $mutation = $operation->method !== 'GET';
        $idempotency = $mutation ? IdempotencyRequirement::Required : IdempotencyRequirement::Forbidden;
        $effect = $mutation ? 'tenant_transactional' : null;

        if ($operation->ability !== $scope
            || $operation->requiredScopes !== [$scope]
            || $operation->idempotency !== $idempotency
            || $operation->effectMode !== $effect) {
            throw new ApiError('internal_error', 500, 'Webhook operation contract is unavailable.');
        }
    }
}
