<?php

declare(strict_types=1);

namespace CoreX\Operations\Idempotency\Internal;

use Closure;
use CoreX\Context\Contracts\CurrentWorkScope;
use CoreX\Context\Contracts\DataScopeFactory;
use CoreX\Data\Data\DataScope;
use CoreX\Data\Laravel\JsonDocuments;
use CoreX\Errors\Enums\CoreErrorCode;
use CoreX\Foundation\Contracts\ReferenceAdmission;
use CoreX\Foundation\Data\ModuleRef;
use CoreX\Operations\Authorization\Contracts\ClientScopeAuthorizer;
use CoreX\Operations\Authorization\Contracts\OperationAuthorizer;
use CoreX\Operations\Authorization\Contracts\OperationFieldAuthorizer;
use CoreX\Operations\Authorization\Contracts\OperationResourceAuthorizer;
use CoreX\Operations\Authorization\Contracts\ReplayAuthorizer;
use CoreX\Operations\Authorization\Laravel\DeclaredFieldAuthorizer;
use CoreX\Operations\Bulk\Laravel\BulkReferences;
use CoreX\Operations\Bulk\Laravel\BulkSubmitGateway;
use CoreX\Operations\Bulk\Laravel\SubmitBulkOperation;
use CoreX\Operations\Cost\Contracts\BudgetExecution as BudgetAdmission;
use CoreX\Operations\Cost\Data\BudgetAttempt;
use CoreX\Operations\Cost\Data\BudgetInvocationResult;
use CoreX\Operations\Cost\Data\BudgetReceipt;
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
use CoreX\Operations\Invocation\Contracts\ClientRequestContext;
use CoreX\Operations\Invocation\Contracts\OperationInputAdmission;
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
final readonly class IdempotentOperationInvoker implements OperationInputAdmission, OperationInvoker
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
        /** @var null|Closure(OperationInvocation): OperationInvocation */
        private ?Closure $inputs = null,
        private ?OperationResourceAuthorizer $resources = null,
        /** @var null|Closure(): BulkSubmitGateway */
        private ?Closure $bulkGateway = null,
        /** @var null|Closure(OperationInvocation): ?OperationResourceAuthorizer */
        private ?Closure $ownerPolicy = null,
    ) {}

    #[Override]
    public function invoke(OperationInvocation $invocation): OperationResult
    {
        [$invocation, $key] = $this->admitInput($invocation);

        if ($key === null) {
            $result = $this->inner->invoke($invocation);
            $fresh = StoredOperationOutcome::success(data: $result->data, accepted: $result->accepted);
            $projected = $this->project(invocation: $invocation, outcome: $fresh, replayed: $result->replayed);

            return new OperationResult(
                data: JsonDocuments::capture(JsonDocuments::array($projected->data)),
                replayed: $projected->replayed,
                metadata: $result->metadata,
                accepted: $projected->accepted,
                privateWitness: $result->privateWitness,
            );
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
            ? ($this->bulk ?? throw new ApiError(errorCode: OperationsErrorCode::CapabilityUnavailable, status: 503, message: 'Bulk admission is unavailable.'))()
            : null;

        try {
            [$outcome, $replayed] = $this->connection->transaction(function () use (
                &$invocation,
                $scope,
                $key,
                &$fingerprint,
                &$handlerFailure,
                &$effectCompleted,
                &$terminalPersisted,
                &$freshMetadata,
                $bulk,
            ): array {
                $this->current->assert(scope: $scope->scope, connection: $this->connection);

                if ($bulk !== null) {
                    $definition = $bulk->prepare(invocation: $invocation, scope: $scope->scope, key: $key, connection: $this->connection);
                    $invocation = new OperationInvocation(operation: $invocation->operation, context: $invocation->context,
                        input: $invocation->input, parameters: $invocation->parameters, idempotencyKey: $invocation->idempotencyKey,
                        clientId: $invocation->clientId, bulk: $definition);
                    $fingerprint = OperationFingerprint::request($invocation);
                } else {
                    $this->enlistOutcome(scope: $scope, invocation: $invocation);
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

                $outcome = StoredOperationOutcome::success(data: $result->data, budgetReceipt: $execution->receipt, accepted: $result->accepted);
                $store->complete($attempt, $outcome->canonicalJson());
                $terminalPersisted = true;

                if ($outcome->budgetReceipt !== null) {
                    $receipt = $outcome->budgetReceipt;
                    $this->connection->afterCommit(fn () => $this->recoverBudget($receipt));
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

                return $this->replay(invocation: $invocation, outcome: $outcome, scope: $scope->scope);
            }

            if ($effectCompleted && $terminalPersisted) {
                throw self::reconciliationRequired();
            }

            if ($error instanceof BudgetStoreUnavailable || $error instanceof BudgetRateLimited) {
                throw $error;
            }

            throw $this->errors->map($error);
        } finally {
            $bulk?->clear($invocation);
        }

        if ($replayed) {
            return $this->replay(invocation: $invocation, outcome: $outcome, scope: $scope->scope);
        }
        $projected = $this->project(invocation: $invocation, outcome: $outcome, replayed: false, scope: $scope->scope);

        return new OperationResult(
            data: JsonDocuments::capture(JsonDocuments::array($projected->data)),
            replayed: false,
            metadata: JsonDocuments::capture($freshMetadata),
            accepted: $projected->accepted,
        );
    }

    #[Override]
    public function assertInput(OperationInvocation $invocation): void
    {
        $this->admitInput($invocation);
    }

    /** @return array{OperationInvocation, ?string} */
    private function admitInput(OperationInvocation $invocation): array
    {
        if ($invocation->context instanceof ClientRequestContext && $invocation->clientId !== $invocation->context->clientId()) {
            throw new ApiError(errorCode: OperationsErrorCode::AccessDenied, status: 403, message: 'The client context does not match the invocation.');
        }

        $this->admitOperation($invocation);

        if ($invocation->operation->handler === BulkSubmitGateway::class) {
            $this->admitKey($invocation);
            $invocation = ($this->bulkGateway ?? throw new ApiError(
                errorCode: OperationsErrorCode::CapabilityUnavailable, status: 503, message: 'Bulk submission is unavailable.',
            ))()->target($invocation);
            $this->admitOperation($invocation);
        }

        $invocation = $this->inputs === null ? $invocation : ($this->inputs)($invocation);
        $key = $this->admitKey($invocation);
        $this->resourcePolicy($invocation);

        if ($key !== null && ! in_array($invocation->operation->effectMode, ['tenant_transactional', 'same_transaction_outbox'], true)) {
            throw new ApiError(errorCode: OperationsErrorCode::IdempotencyNotSupported, status: 422, message: 'This operation does not support transactional idempotency.');
        }

        return [$invocation, $key];
    }

    private function replay(OperationInvocation $invocation, StoredOperationOutcome $outcome, DataScope $scope): OperationResult
    {
        $projected = $this->project(invocation: $invocation, outcome: $outcome, replayed: true, scope: $scope);

        if ($outcome->budgetReceipt !== null) {
            $this->recoverBudget($outcome->budgetReceipt);
        }

        return $projected;
    }

    private function recoverBudget(BudgetReceipt $receipt): void
    {
        try {
            $this->budget?->recover($receipt);
        } catch (Throwable) {
            throw self::reconciliationRequired();
        }
    }

    private static function reconciliationRequired(): ApiError
    {
        return new ApiError(
            errorCode: OperationsErrorCode::ReconciliationRequired,
            status: 409,
            message: 'The operation outcome must be reconciled before retry.',
        );
    }

    private function admitOperation(OperationInvocation $invocation): void
    {
        $operation = $invocation->operation;

        if ($operation->version !== 'v2' || ! $operation->executionSupported) {
            throw new ApiError(errorCode: OperationsErrorCode::OperationUnsupported, status: 404, message: 'Operation is not executable.');
        }

        if (! $this->modules->isActive(name: $operation->module, ctx: $invocation->context->tenant())) {
            throw new ApiError(errorCode: OperationsErrorCode::ModuleUnavailable, status: 404, message: 'Operation not found.');
        }

        if (! $this->clientScopes->allows(operation: $operation, context: $invocation->context)) {
            throw new ApiError(errorCode: OperationsErrorCode::ScopeRequired, status: 403, message: 'The client lacks a required operation scope.');
        }

        if (! $this->authorizer->allows(operation: $operation, context: $invocation->context)) {
            throw new ApiError(errorCode: OperationsErrorCode::AccessDenied, status: 403, message: 'Operation is not permitted.');
        }

        $path = DeclaredFieldAuthorizer::firstForbiddenWrite(operation: $operation, context: $invocation->context, authorizer: $this->fields, input: JsonDocuments::array($invocation->input));

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

        $targets = $invocation->bulk === null ? [] : BulkReferences::targets($invocation->bulk);
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
                $this->current->assert(scope: $scope->scope, connection: $this->connection);
                $this->enlistOutcome(scope: $scope, invocation: $invocation);
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
                throw self::reconciliationRequired();
            }

            throw $this->errors->map($persistenceError);
        }
    }

    private function project(
        OperationInvocation $invocation,
        StoredOperationOutcome $outcome,
        bool $replayed,
        ?DataScope $scope = null,
    ): OperationResult {
        if ($scope !== null) {
            $this->current->assert($scope);
        }

        if ($replayed) {
            $this->admitOperation($invocation);
        }
        $this->resourcePolicy(invocation: $invocation, outcome: $outcome);
        $authorized = $this->replayAuthorizer->authorize(invocation: $invocation, outcome: $outcome, replayed: $replayed);

        if ($authorized->kind === 'error') {
            throw $authorized->error($this->problems);
        }

        $result = $authorized->result($replayed);

        if ($result->accepted !== null && $invocation->operation->handler === SubmitBulkOperation::class) {
            $handler = ($this->bulk ?? throw new ApiError(
                errorCode: OperationsErrorCode::CapabilityUnavailable, status: 503, message: 'Bulk admission is unavailable.',
            ))();
            $accepted = $handler->release(invocation: $invocation, accepted: $result->accepted);

            return new OperationResult(data: $result->data, replayed: $result->replayed, metadata: $result->metadata, accepted: $accepted);
        }

        return $result;
    }

    private function resourcePolicy(OperationInvocation $invocation, ?StoredOperationOutcome $outcome = null): void
    {
        $ownerPolicy = $this->ownerPolicy === null ? null : ($this->ownerPolicy)($invocation);
        $ownerPolicy?->authorize(invocation: $invocation, outcome: $outcome);

        if (! in_array('operations.resource_policy.v2', $invocation->operation->capabilities, true)) {
            return;
        }

        ($this->resources ?? throw new ApiError(
            errorCode: OperationsErrorCode::CapabilityUnavailable, status: 503, message: 'Resource authorization is unavailable.',
        ))->authorize(invocation: $invocation, outcome: $outcome);
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
