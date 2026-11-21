<?php

declare(strict_types=1);

namespace CoreX\Operations\Idempotency\Internal;

use Closure;
use CoreX\Context\Contracts\CurrentWorkScope;
use CoreX\Context\Contracts\DataScopeFactory;
use CoreX\Data\Laravel\JsonDocuments;
use CoreX\Errors\Enums\CoreErrorCode;
use CoreX\Foundation\Contracts\ReferenceAdmission;
use CoreX\Foundation\Data\ModuleRef;
use CoreX\Operations\Authorization\Contracts\ClientScopeAuthorizer;
use CoreX\Operations\Authorization\Contracts\OperationAuthorizer;
use CoreX\Operations\Authorization\Contracts\OperationFieldAuthorizer;
use CoreX\Operations\Authorization\Contracts\ReplayAuthorizer;
use CoreX\Operations\Authorization\Laravel\DeclaredFieldAuthorizer;
use CoreX\Operations\Bulk\Laravel\BulkReferences;
use CoreX\Operations\Bulk\Laravel\SubmitBulkOperation;
use CoreX\Operations\Cost\Contracts\BudgetExecution as BudgetAdmission;
use CoreX\Operations\Cost\Data\BudgetAttempt;
use CoreX\Operations\Cost\Data\BudgetInvocationResult;
use CoreX\Operations\Cost\Exceptions\BudgetRateLimited;
use CoreX\Operations\Cost\Exceptions\BudgetStoreUnavailable;
use CoreX\Operations\Definition\Laravel\OperationFingerprint;
use CoreX\Operations\Errors\Contracts\ErrorMapping as ErrorMapper;
use CoreX\Operations\Errors\Enums\OperationsErrorCode;
use CoreX\Operations\Errors\Exceptions\ApiError;
use CoreX\Operations\Idempotency\Contracts\IdempotencyStore;
use CoreX\Operations\Idempotency\Data\IdempotencyAttempt;
use CoreX\Operations\Idempotency\Data\IdempotencyScope;
use CoreX\Operations\Idempotency\Data\StoredOperationOutcome;
use CoreX\Operations\Idempotency\Enums\IdempotencyRequirement;
use CoreX\Operations\Invocation\Contracts\OperationInvoker;
use CoreX\Operations\Invocation\Contracts\TransactionalOperationInvoker;
use CoreX\Operations\Invocation\Data\OperationInvocation;
use CoreX\Operations\Invocation\Data\OperationResult;
use Illuminate\Database\Connection;
use Override;
use Packman\Attributes\Internal;
use Packman\Errors\Contracts\ProblemRegistry;
use Packman\Modules\Contracts\Registry\ModuleRegistry;
use Throwable;

/** @internal Adds tenant-transactional idempotency around the shared operation invoker. */
#[Internal]
final readonly class IdempotentOperationInvoker implements OperationInvoker
{
    public function __construct(
        private OperationInvoker $inner,
        /** @var Closure(): IdempotencyStore */
        private Closure $store,
        private Connection $connection,
        private ModuleRegistry $modules,
        private DataScopeFactory $scopes,
        private CurrentWorkScope $current,
        private ClientScopeAuthorizer $clientScopes,
        private OperationFieldAuthorizer $fields,
        private OperationAuthorizer $authorizer,
        private ReplayAuthorizer $replayAuthorizer,
        private ErrorMapper $errors,
        private ProblemRegistry $problems,
        private ?BudgetAdmission $budget = null,
        /** @var null|Closure(): SubmitBulkOperation */
        private ?Closure $bulk = null,
        private ?ReferenceAdmission $references = null,
    ) {}

    #[Override]
    public function invoke(OperationInvocation $invocation): OperationResult
    {
        if ($invocation->operation->idempotency === IdempotencyRequirement::Required
            || $invocation->idempotencyKey !== null) {
            $this->admitOperation($invocation);
        }

        $key = $this->admitKey($invocation);

        if ($key === null) {
            $result = $this->inner->invoke($invocation);
            $fresh = StoredOperationOutcome::success(JsonDocuments::capture(JsonDocuments::array($result->data)));
            $projected = $this->project(invocation: $invocation, outcome: $fresh, replayed: $result->replayed);

            return new OperationResult(
                data: JsonDocuments::capture(JsonDocuments::array($projected->data)),
                replayed: $projected->replayed,
                metadata: JsonDocuments::capture(JsonDocuments::map($result->metadata)),
            );
        }

        if (! in_array($invocation->operation->effectMode, ['tenant_transactional', 'same_transaction_outbox'], true)) {
            throw new ApiError(errorCode: OperationsErrorCode::IdempotencyNotSupported, status: 422, message: 'This operation does not support transactional idempotency.');
        }

        $dataScope = $this->scopes->forCurrentPrincipal($invocation->context->tenant());
        $principal = $invocation->context->principal();

        if ($dataScope->principalKind !== $principal->principalType()->value || $dataScope->principalId !== $principal->principalId()) {
            throw new ApiError(errorCode: OperationsErrorCode::AccessDenied, status: 403, message: 'The invocation principal differs from the authenticated scope.');
        }

        $scope = OperationFingerprint::scope(invocation: $invocation, scope: $dataScope);
        $fingerprint = OperationFingerprint::request($invocation);
        $handlerFailure = new HandlerFailure;
        $effectCompleted = false;
        $terminalPersisted = false;
        $freshMetadata = [];
        $bulk = $invocation->operation->handler === SubmitBulkOperation::class
            ? ($this->bulk ?? throw new ApiError(OperationsErrorCode::CapabilityUnavailable, 503, 'Bulk admission is unavailable.'))()
            : null;

        try {
            [$outcome, $replayed] = $this->connection->transaction(function () use (
                $invocation,
                $scope,
                $key,
                $fingerprint,
                &$handlerFailure,
                &$effectCompleted,
                &$terminalPersisted,
                &$freshMetadata,
                $bulk,
            ): array {
                $this->current->assert($scope->scope, $this->connection);

                if ($bulk !== null) {
                    $bulk->prepare($invocation, $scope->scope, $key, $this->connection);
                } else {
                    $this->enlistOutcome($scope, $invocation);
                }
                $store = ($this->store)();
                $attempt = $store->begin(
                    scope: $scope,
                    key: $key,
                    requestFingerprint: $fingerprint,
                    contractHash: $invocation->operation->contractHash,
                );

                if ($attempt->disposition === IdempotencyAttempt::Replay) {
                    return [StoredOperationOutcome::fromCanonicalJson(json: (string) $attempt->outcomeJson, problems: $this->problems), true];
                }

                $this->throwUnlessOwner(attempt: $attempt, contractHash: $invocation->operation->contractHash);

                // Acquiring the owner row proves that no earlier same-transaction effect committed.
                $budgetAttempt = new BudgetAttempt(hash('sha256', json_encode([
                    'corex.operation-budget/2', $attempt->scopeHash, $attempt->keyHash, $attempt->requestFingerprint,
                    $scope->scope->bindingId, $scope->scope->bindingGeneration, $scope->scope->restoreEpoch,
                ], JSON_THROW_ON_ERROR)));

                try {
                    $execution = $this->inner instanceof TransactionalOperationInvoker
                        ? $this->inner->invokeTransactional($invocation, $budgetAttempt)
                        : new BudgetInvocationResult($this->inner->invoke($invocation));
                    $result = $execution->result;
                    $effectCompleted = true;
                    $freshMetadata = JsonDocuments::map($result->metadata);
                } catch (BudgetStoreUnavailable|BudgetRateLimited $error) {
                    throw $error;
                } catch (Throwable $error) {
                    $handlerFailure->error = $this->errors->map($error);

                    throw $error;
                }

                $outcome = StoredOperationOutcome::success(JsonDocuments::capture(JsonDocuments::array($result->data)), $execution->receipt);
                $store->complete($attempt, $outcome->canonicalJson());
                $terminalPersisted = true;

                if ($outcome->budgetReceipt !== null) {
                    $receipt = $outcome->budgetReceipt;
                    $this->connection->afterCommit(fn () => $this->budget?->recover($receipt));
                }

                return [$outcome, false];
            });
        } catch (Throwable $error) {
            if ($handlerFailure->error instanceof ApiError) {
                if ($this->isPreAdmissionFailure($handlerFailure->error)) {
                    throw $handlerFailure->error;
                }

                $outcome = $this->persistFailure(
                    invocation: $invocation,
                    scope: $scope,
                    key: $key,
                    fingerprint: $fingerprint,
                    error: $handlerFailure->error,
                );

                return $this->replay($invocation, $outcome);
            }

            if ($effectCompleted && $terminalPersisted) {
                throw new ApiError(
                    errorCode: OperationsErrorCode::ReconciliationRequired,
                    status: 409,
                    message: 'The operation outcome must be reconciled before retry.',
                );
            }

            if ($error instanceof BudgetStoreUnavailable || $error instanceof BudgetRateLimited) {
                throw $error;
            }

            throw $this->errors->map($error);
        } finally {
            $bulk?->clear($invocation);
        }

        if ($replayed) {
            return $this->replay($invocation, $outcome);
        }
        $projected = $this->project(invocation: $invocation, outcome: $outcome, replayed: false);

        return new OperationResult(
            data: JsonDocuments::capture(JsonDocuments::array($projected->data)),
            replayed: false,
            metadata: JsonDocuments::capture($freshMetadata),
        );
    }

    private function replay(OperationInvocation $invocation, StoredOperationOutcome $outcome): OperationResult
    {
        $projected = $this->project(invocation: $invocation, outcome: $outcome, replayed: true);

        if ($outcome->budgetReceipt !== null) {
            $this->budget?->recover($outcome->budgetReceipt);
        }

        return $this->budget?->replay($invocation, $projected) ?? $projected;
    }

    private function admitOperation(OperationInvocation $invocation): void
    {
        $operation = $invocation->operation;

        if (! $operation->executionSupported) {
            throw new ApiError(errorCode: OperationsErrorCode::OperationUnsupported, status: 404, message: 'Operation is not executable.');
        }

        if (! $this->modules->isActive(name: $operation->module, ctx: $invocation->context->tenant())) {
            throw new ApiError(errorCode: OperationsErrorCode::ModuleUnavailable, status: 404, message: 'Operation not found.');
        }

        if (! $this->clientScopes->allows($operation, $invocation->context)) {
            throw new ApiError(errorCode: OperationsErrorCode::ScopeRequired, status: 403, message: 'The client lacks a required operation scope.');
        }

        if (! $this->authorizer->allows(operation: $operation, context: $invocation->context)) {
            throw new ApiError(errorCode: OperationsErrorCode::Forbidden, status: 403, message: 'Operation is not permitted.');
        }

        $path = DeclaredFieldAuthorizer::firstForbiddenWrite($operation, $invocation->context, $this->fields, JsonDocuments::map($invocation->input));

        if ($path !== null) {
            throw new ApiError(errorCode: OperationsErrorCode::AccessDenied, status: 403, message: 'An input field is not writable.', path: $path);
        }
    }

    private function admitKey(OperationInvocation $invocation): ?string
    {
        $key = $invocation->idempotencyKey;
        $requirement = $invocation->operation->idempotency;

        if ($requirement === IdempotencyRequirement::Forbidden && $key !== null) {
            throw new ApiError(errorCode: OperationsErrorCode::IdempotencyKeyForbidden, status: 422, message: 'An idempotency key is not allowed for this operation.');
        }

        if ($requirement === IdempotencyRequirement::Required && $key === null) {
            throw new ApiError(errorCode: OperationsErrorCode::IdempotencyKeyRequired, status: 422, message: 'An idempotency key is required for this operation.');
        }

        if ($key !== null && (strlen($key) < 1 || strlen($key) > 255)) {
            throw new ApiError(errorCode: OperationsErrorCode::IdempotencyKeyInvalid, status: 422, message: 'The idempotency key must contain 1-255 bytes.');
        }

        return $key;
    }

    private function throwUnlessOwner(IdempotencyAttempt $attempt, string $contractHash): void
    {
        if ($attempt->ownsLease()) {
            return;
        }

        if (! hash_equals($attempt->contractHash, $contractHash)) {
            throw new ApiError(errorCode: OperationsErrorCode::IdempotencyContractMismatch, status: 409, message: 'The stored outcome belongs to a different operation contract.');
        }

        throw match ($attempt->disposition) {
            IdempotencyAttempt::Conflict => new ApiError(errorCode: OperationsErrorCode::IdempotencyConflict, status: 409, message: 'The idempotency key was used with different input.'),
            IdempotencyAttempt::ContractMismatch => new ApiError(errorCode: OperationsErrorCode::IdempotencyContractMismatch, status: 409, message: 'The stored outcome belongs to a different operation contract.'),
            IdempotencyAttempt::InProgress => new ApiError(errorCode: OperationsErrorCode::IdempotencyInProgress, status: 409, message: 'The operation is already in progress.', retryable: true),
            default => new ApiError(errorCode: CoreErrorCode::InternalError, status: 500, message: 'Operation could not be completed.'),
        };
    }

    private function enlistOutcome(IdempotencyScope $scope, OperationInvocation $invocation): void
    {
        if ($this->references === null) {
            return;
        }

        $stamp = $invocation->operation->handler === SubmitBulkOperation::class
            ? JsonDocuments::map($invocation->input)['extension_stamp'] ?? []
            : [];
        $targets = is_array($stamp) ? BulkReferences::stampTargets($stamp) : [];
        $this->references->enlist($scope->scope, $this->connection, new ModuleRef('axiomasoft/corex-operations'), new ModuleRef($invocation->operation->module), ...$targets);
    }

    private function persistFailure(
        OperationInvocation $invocation,
        IdempotencyScope $scope,
        string $key,
        string $fingerprint,
        ApiError $error,
    ): StoredOperationOutcome {
        $terminalPersisted = false;

        try {
            return $this->connection->transaction(function () use (
                $invocation,
                $scope,
                $key,
                $fingerprint,
                $error,
                &$terminalPersisted,
            ): StoredOperationOutcome {
                $this->current->assert($scope->scope, $this->connection);
                $this->enlistOutcome($scope, $invocation);
                $store = ($this->store)();
                $attempt = $store->begin(
                    scope: $scope,
                    key: $key,
                    requestFingerprint: $fingerprint,
                    contractHash: $invocation->operation->contractHash,
                );

                if ($attempt->disposition === IdempotencyAttempt::Replay) {
                    return StoredOperationOutcome::fromCanonicalJson(json: (string) $attempt->outcomeJson, problems: $this->problems);
                }

                $this->throwUnlessOwner(attempt: $attempt, contractHash: $invocation->operation->contractHash);
                $outcome = StoredOperationOutcome::failure($error);
                $store->fail($attempt, $outcome->canonicalJson());
                $terminalPersisted = true;

                return $outcome;
            });
        } catch (Throwable $persistenceError) {
            if ($terminalPersisted) {
                throw new ApiError(
                    errorCode: OperationsErrorCode::ReconciliationRequired,
                    status: 409,
                    message: 'The operation outcome must be reconciled before retry.',
                );
            }

            throw $this->errors->map($persistenceError);
        }
    }

    private function project(
        OperationInvocation $invocation,
        StoredOperationOutcome $outcome,
        bool $replayed,
    ): OperationResult {
        $authorized = $this->replayAuthorizer->authorize(invocation: $invocation, outcome: $outcome, replayed: $replayed);

        if ($authorized->kind === 'error') {
            throw $authorized->error($this->problems);
        }

        return $authorized->result($replayed);
    }

    private function isPreAdmissionFailure(ApiError $error): bool
    {
        return in_array($error->errorDescriptor()->errorCode, [
            OperationsErrorCode::AccessDenied,
            OperationsErrorCode::AuthenticationRequired,
            OperationsErrorCode::Forbidden,
            OperationsErrorCode::ModuleUnavailable,
            OperationsErrorCode::OperationNotFound,
            OperationsErrorCode::OperationUnsupported,
            OperationsErrorCode::ScopeRequired,
            OperationsErrorCode::ValidationFailed,
        ], true);
    }
}
