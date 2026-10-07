<?php

declare(strict_types=1);

namespace CoreX\Operations\Idempotency\Internal;

use Closure;
use CoreX\Data\Laravel\JsonDocuments;
use CoreX\Errors\Contracts\ProblemRegistry;
use CoreX\Errors\Enums\CoreErrorCode;
use CoreX\Modules\Contracts\ModuleRegistry;
use CoreX\Operations\Authorization\Contracts\OperationAuthorizer;
use CoreX\Operations\Authorization\Contracts\ReplayAuthorizer;
use CoreX\Operations\Cost\Contracts\BudgetExecution as BudgetAdmission;
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
use CoreX\Operations\Invocation\Data\OperationInvocation;
use CoreX\Operations\Invocation\Data\OperationResult;
use Illuminate\Database\Connection;
use Override;
use Throwable;

/** @internal Adds tenant-transactional idempotency around the shared operation invoker. */
final readonly class IdempotentOperationInvoker implements OperationInvoker
{
    public function __construct(
        private OperationInvoker $inner,
        /** @var Closure(): IdempotencyStore */
        private Closure $store,
        private Connection $connection,
        private ModuleRegistry $modules,
        private OperationAuthorizer $authorizer,
        private ReplayAuthorizer $replayAuthorizer,
        private ErrorMapper $errors,
        private ProblemRegistry $problems,
        private ?BudgetAdmission $budget = null,
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

        $scope = OperationFingerprint::scope(invocation: $invocation, connection: $this->connection);
        $fingerprint = OperationFingerprint::request($invocation);
        $handlerFailure = new HandlerFailure;
        $effectCompleted = false;
        $terminalPersisted = false;
        $freshMetadata = [];

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
            ): array {
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

                try {
                    $result = $this->inner->invoke($invocation);
                    $effectCompleted = true;
                    $freshMetadata = JsonDocuments::map($result->metadata);
                } catch (BudgetStoreUnavailable|BudgetRateLimited $error) {
                    throw $error;
                } catch (Throwable $error) {
                    $handlerFailure->error = $this->errors->map($error);

                    throw $error;
                }

                $outcome = StoredOperationOutcome::success(JsonDocuments::capture(JsonDocuments::array($result->data)));
                $store->complete($attempt, $outcome->canonicalJson());
                $terminalPersisted = true;

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

                if ($outcome->kind === 'error') {
                    throw $outcome->error($this->problems);
                }

                return $this->project(invocation: $invocation, outcome: $outcome, replayed: true);
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
        }

        $projected = $this->project(invocation: $invocation, outcome: $outcome, replayed: $replayed);

        if ($replayed && $this->budget !== null) {
            return $this->budget->replay(invocation: $invocation, result: $projected);
        }

        return new OperationResult(
            data: JsonDocuments::capture(JsonDocuments::array($projected->data)),
            replayed: $projected->replayed,
            metadata: JsonDocuments::capture($freshMetadata),
        );
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

        if (! $this->authorizer->allows(operation: $operation, context: $invocation->context)) {
            throw new ApiError(errorCode: OperationsErrorCode::Forbidden, status: 403, message: 'Operation is not permitted.');
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
