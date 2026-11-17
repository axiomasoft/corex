<?php

declare(strict_types=1);

namespace CoreX\Operations\Idempotency\Internal;

use CoreX\Data\Laravel\JsonDocuments;
use CoreX\Operations\Authorization\Contracts\ClientScopeAuthorizer;
use CoreX\Operations\Authorization\Contracts\OperationAuthorizer;
use CoreX\Operations\Authorization\Contracts\OperationFieldAuthorizer;
use CoreX\Operations\Authorization\Contracts\ReplayAuthorizer;
use CoreX\Operations\Authorization\Laravel\DeclaredFieldAuthorizer;
use CoreX\Operations\Errors\Enums\OperationsErrorCode;
use CoreX\Operations\Errors\Exceptions\ApiError;
use CoreX\Operations\Idempotency\Data\StoredOperationOutcome;
use CoreX\Operations\Invocation\Data\OperationInvocation;
use Override;
use Packman\Modules\Contracts\Registry\ModuleRegistry;

/** Re-checks module, client scope, operation and field access before a stored outcome is replayed. */
final readonly class DefaultReplayAuthorizer implements ReplayAuthorizer
{
    public function __construct(
        private ModuleRegistry $modules,
        private ClientScopeAuthorizer $clientScopes,
        private OperationAuthorizer $authorizer,
        private OperationFieldAuthorizer $fields,
    ) {}

    #[Override]
    public function authorize(
        OperationInvocation $invocation,
        StoredOperationOutcome $outcome,
        bool $replayed,
    ): StoredOperationOutcome {
        if ($replayed && ! $this->modules->isActive(name: $invocation->operation->module, ctx: $invocation->context->tenant())) {
            throw new ApiError(errorCode: OperationsErrorCode::ModuleUnavailable, status: 404, message: 'Operation not found.');
        }

        if ($replayed && ! $this->clientScopes->allows(operation: $invocation->operation, context: $invocation->context)) {
            throw new ApiError(errorCode: OperationsErrorCode::ScopeRequired, status: 403, message: 'The client lacks a required operation scope.');
        }

        if ($replayed && ! $this->authorizer->allows(operation: $invocation->operation, context: $invocation->context)) {
            throw new ApiError(errorCode: OperationsErrorCode::AccessDenied, status: 403, message: 'Operation is not permitted.');
        }

        if ($replayed && $outcome->kind === 'success' && JsonDocuments::optionalArray($outcome->data) !== null) {
            return StoredOperationOutcome::success(JsonDocuments::capture(DeclaredFieldAuthorizer::prune(
                operation: $invocation->operation,
                context: $invocation->context,
                authorizer: $this->fields,
                data: JsonDocuments::optionalArray($outcome->data),
            )));
        }

        return $outcome;
    }
}
