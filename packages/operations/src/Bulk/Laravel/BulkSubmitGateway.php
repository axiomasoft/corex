<?php

declare(strict_types=1);

namespace CoreX\Operations\Bulk\Laravel;

use CoreX\Invocation\Contracts\OperationHandler;
use CoreX\Invocation\Contracts\RequestContext;
use CoreX\Operations\Bulk\Contracts\BulkTargetResolver;
use CoreX\Operations\Definition\Contracts\OperationRegistry;
use CoreX\Operations\Definition\Data\Operation;
use CoreX\Operations\Definition\Enums\OperationMode;
use CoreX\Operations\Errors\Enums\OperationsErrorCode;
use CoreX\Operations\Errors\Exceptions\ApiError;
use CoreX\Operations\Invocation\Contracts\ClientRequestContext;
use CoreX\Operations\Invocation\Data\OperationInvocation;
use CoreX\Operations\Invocation\Internal\OperationInput;
use CoreX\Operations\Invocation\Laravel\AuthenticatedOperationContext;
use Illuminate\Contracts\Container\Container;
use Packman\Attributes\Internal;

#[Internal]
final readonly class BulkSubmitGateway implements OperationHandler
{
    public function __construct(
        private Container $container,
        private OperationRegistry $operations,
        private OperationInput $inputs,
    ) {}

    public function target(OperationInvocation $invocation): OperationInvocation
    {
        $context = $invocation->context;

        if (! $this->container->bound(BulkTargetResolver::class)) {
            throw new ApiError(errorCode: OperationsErrorCode::CapabilityUnavailable, status: 503, message: 'Bulk submission is unavailable.');
        }
        $invocation = $this->inputs->normalize($invocation);
        $id = $this->container->make(BulkTargetResolver::class)->operationId(context: $context, input: $invocation->input);
        $target = array_find($this->operations->all('v2'), static fn (Operation $operation): bool => $operation->id === $id);

        if ($target === null || $target->mode !== OperationMode::BulkCommand || ! $target->executionSupported
            || $target->handler !== SubmitBulkOperation::class) {
            throw new ApiError(errorCode: OperationsErrorCode::CapabilityUnavailable, status: 503, message: 'Bulk submission is unavailable.');
        }

        if (! $context instanceof ClientRequestContext) {
            throw new ApiError(errorCode: OperationsErrorCode::AuthenticationRequired, status: 401, message: 'Authentication is required.');
        }
        $context = AuthenticatedOperationContext::forOperation(context: $context, operation: $target);

        return new OperationInvocation(operation: $target, context: $context, input: $invocation->input,
            parameters: [], idempotencyKey: $invocation->idempotencyKey, clientId: $invocation->clientId);
    }

    public function handle(RequestContext $context, array $input, array $parameters): array
    {
        throw new ApiError(errorCode: OperationsErrorCode::OperationUnsupported, status: 404, message: 'Operation is not executable.');
    }
}
