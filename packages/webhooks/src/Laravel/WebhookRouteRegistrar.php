<?php

declare(strict_types=1);

namespace CoreX\Webhooks\Laravel;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use CoreX\Errors\Enums\CoreErrorCode;
use CoreX\Operations\Definition\Contracts\OperationRegistry;
use CoreX\Operations\Definition\Data\Operation;
use CoreX\Operations\Errors\Enums\OperationsErrorCode;
use CoreX\Operations\Errors\Exceptions\ApiError;
use CoreX\Operations\Idempotency\Enums\IdempotencyRequirement;
use CoreX\Operations\Invocation\Contracts\OperationDispatcher;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

#[Spi(kind: SpiKind::Call, since: '1.0')]
final readonly class WebhookRouteRegistrar
{
    public static function register(Application $app): void
    {
        (new WebhookHttpRegistrar)->boot($app);
    }

    public function forward(Request $request, OperationDispatcher $dispatcher, OperationRegistry $operations): JsonResponse
    {
        $path = ltrim($request->path(), '/');
        $marker = 'v1/';
        $offset = str_starts_with($path, $marker) ? 0 : strpos($path, '/'.$marker);

        if ($offset === false) {
            throw new ApiError(OperationsErrorCode::OperationNotFound, 404, 'Operation not found.');
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
            throw new ApiError(CoreErrorCode::InternalError, 500, 'Webhook operation contract is unavailable.');
        }
    }
}
