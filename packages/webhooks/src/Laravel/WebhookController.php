<?php

declare(strict_types=1);

namespace CoreX\Webhooks\Laravel;

use CoreX\Errors\Enums\CoreErrorCode;
use CoreX\Operations\Definition\Contracts\OperationRegistry;
use CoreX\Operations\Definition\Data\Operation;
use CoreX\Operations\Errors\Enums\OperationsErrorCode;
use CoreX\Operations\Errors\Exceptions\ApiError;
use CoreX\Operations\Idempotency\Enums\IdempotencyRequirement;
use CoreX\Operations\Laravel\Contracts\HttpOperationDispatcher;
use CoreX\Operations\Laravel\Support\ApiOptions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Packman\Attributes\Spi;
use Packman\Attributes\SpiKind;

#[Spi(kind: SpiKind::Call, since: '1.0')]
final readonly class WebhookController
{
    public function forward(Request $request, HttpOperationDispatcher $dispatcher, OperationRegistry $operations, ApiOptions $options): JsonResponse
    {
        $path = ltrim($request->path(), '/');
        $prefix = ltrim($options->publicBaseUri(), '/').'/';

        if (! str_starts_with($path, $prefix)) {
            throw new ApiError(errorCode: OperationsErrorCode::OperationNotFound, status: 404, message: 'Operation not found.');
        }

        $relative = substr($path, strlen($prefix));
        [$operation] = $operations->match(version: 'v2', method: $request->method(), path: '/'.$relative);
        self::assertOperationContract($operation);

        return $dispatcher->dispatch(request: $request, version: 'v2', path: $relative);
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
            throw new ApiError(errorCode: CoreErrorCode::InternalError, status: 500, message: 'Webhook operation contract is unavailable.');
        }
    }
}
