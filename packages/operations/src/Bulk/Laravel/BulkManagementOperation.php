<?php

declare(strict_types=1);

namespace CoreX\Operations\Bulk\Laravel;

use CoreX\Context\Contracts\DataScopeFactory;
use CoreX\Context\Contracts\WorkScopeRunner;
use CoreX\Data\Data\DataScope;
use CoreX\Data\Laravel\JsonDocuments;
use CoreX\Errors\Enums\CoreErrorCode;
use CoreX\Invocation\Contracts\OperationHandler;
use CoreX\Invocation\Contracts\RequestContext;
use CoreX\Operations\Authorization\Contracts\OperationResourceAuthorizer;
use CoreX\Operations\Bulk\Enums\BulkOperationStatus;
use CoreX\Operations\Bulk\Internal\BulkFailureLimit;
use CoreX\Operations\Bulk\Internal\BulkOperations;
use CoreX\Operations\Errors\Enums\OperationsErrorCode;
use CoreX\Operations\Errors\Exceptions\ApiError;
use CoreX\Operations\Idempotency\Data\StoredOperationOutcome;
use CoreX\Operations\Invocation\Contracts\ClientRequestContext;
use CoreX\Operations\Invocation\Contracts\OperationAcceptance;
use CoreX\Operations\Invocation\Data\AcceptedOperation;
use CoreX\Operations\Invocation\Data\OperationInvocation;
use Illuminate\Contracts\Routing\UrlGenerator;
use Illuminate\Http\JsonResponse;
use Packman\Attributes\Internal;
use Packman\Errors\Enums\ErrorCategory;

#[Internal]
final readonly class BulkManagementOperation implements OperationAcceptance, OperationHandler, OperationResourceAuthorizer
{
    public function __construct(
        private DataScopeFactory $scopes,
        private WorkScopeRunner $runner,
        private BulkOperations $operations,
        private UrlGenerator $urls,
    ) {}

    public function handle(RequestContext $context, array $input, array $parameters): array
    {
        $id = $parameters['id'] ?? null;

        if (! $context instanceof ClientRequestContext || $id === null) {
            throw new ApiError(errorCode: OperationsErrorCode::NotFound, status: 404, message: 'Bulk operation is unavailable.');
        }

        $scope = $this->scope($context);
        $operation = $context->metadata()->operationId;
        $client = $context->clientId();
        $limit = $input['limit'] ?? BulkFailureLimit::DEFAULT;
        $after = $input['after'] ?? null;
        $response = $this->runner->run(scope: $scope, work: fn (): JsonResponse => match ($operation) {
            'bulk-operations.show' => $this->operations->show(scope: $scope, clientId: $client, id: $id),
            'bulk-operations.cancel' => $this->operations->cancel(scope: $scope, clientId: $client, id: $id),
            'bulk-operations.result' => $this->operations->result(scope: $scope, clientId: $client, id: $id),
            'bulk-operations.failures' => $this->operations->failurePage(scope: $scope, clientId: $client, id: $id,
                afterOrdinal: is_string($after) ? $after : null, limit: is_numeric($limit) ? (int) $limit : BulkFailureLimit::DEFAULT),
            default => throw new ApiError(errorCode: OperationsErrorCode::OperationNotFound, status: 404, message: 'Operation not found.'),
        });
        $body = $response->getData(true);

        if (! is_array($body)) {
            throw new ApiError(errorCode: CoreErrorCode::InternalError, status: 500, message: 'Bulk response is unavailable.');
        }

        if ($response->getStatusCode() >= 400) {
            $code = is_string($body['code'] ?? null) ? OperationsErrorCode::tryFrom($body['code']) : null;
            $code ??= match ($response->getStatusCode()) {
                404 => OperationsErrorCode::NotFound,
                422 => OperationsErrorCode::InvalidRequest,
                default => CoreErrorCode::InternalError,
            };

            throw new ApiError(errorCode: $code, status: $response->getStatusCode(), message: 'Bulk operation could not be completed.',
                category: $code === OperationsErrorCode::BulkResultExpired ? ErrorCategory::NotFound : null,
                retryable: $code === OperationsErrorCode::BulkNotReady);
        }

        if ($operation === 'bulk-operations.show' && is_string($body['status'] ?? null)) {
            $status = BulkOperationStatus::tryFrom($body['status']);
            $params = ['id' => $id];

            if ($status === BulkOperationStatus::Pending || $status === BulkOperationStatus::Running) {
                $body['cancel_url'] = $this->urls->route('corex.api.bulk.cancel', $params, false);
            }

            if ($status === BulkOperationStatus::Completed) {
                $body['result_url'] = $this->urls->route('corex.api.bulk.result', $params, false);
            }

            if ($status !== BulkOperationStatus::Expired && ($body['has_failures'] ?? false) === true) {
                $body['failures_url'] = $this->urls->route('corex.api.bulk.failures', $params, false);
            }
        }

        return array_is_list($body) ? $body : JsonDocuments::map(value: JsonDocuments::capture($body));
    }

    public function authorize(OperationInvocation $invocation, ?StoredOperationOutcome $outcome = null): void
    {
        if ($invocation->operation->id !== 'bulk-operations.cancel') {
            return;
        }

        $context = $invocation->context;
        $id = $invocation->parameters['id'] ?? '';

        if (! $context instanceof ClientRequestContext) {
            throw new ApiError(errorCode: OperationsErrorCode::NotFound, status: 404, message: 'Bulk operation is unavailable.');
        }

        $scope = $this->scope($context);
        $available = $this->runner->run(scope: $scope, work: fn (): bool => $this->operations
            ->show(scope: $scope, clientId: $context->clientId(), id: $id)->isSuccessful());

        if (! $available) {
            throw new ApiError(errorCode: OperationsErrorCode::NotFound, status: 404, message: 'Bulk operation is unavailable.');
        }
    }

    private function scope(RequestContext $context): DataScope
    {
        $scope = $this->scopes->forCurrentPrincipal($context->tenant());
        $principal = $context->principal();

        if ($scope->principalId !== $principal->principalId() || $scope->principalKind !== $principal->principalType()->value) {
            throw new ApiError(errorCode: OperationsErrorCode::AccessDenied, status: 403, message: 'The invocation scope is not authorized.');
        }

        return $scope;
    }

    public function acceptance(RequestContext $context, array $result): ?AcceptedOperation
    {
        $id = $result['operation_id'] ?? null;

        if ($context->metadata()->operationId !== 'bulk-operations.cancel' || ($result['status'] ?? null) !== 'cancelling' || ! is_string($id)) {
            return null;
        }

        return new AcceptedOperation(operationRef: $id, statusUri: $this->urls->route('corex.api.bulk.show', ['id' => $id], false));
    }
}
