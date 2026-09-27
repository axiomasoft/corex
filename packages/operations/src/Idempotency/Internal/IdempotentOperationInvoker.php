<?php

declare(strict_types=1);

namespace CoreX\Api\Internal;

use Closure;
use CoreX\Api\ApiError;
use CoreX\Api\Contracts\IdempotencyStore;
use CoreX\Api\Contracts\OperationAuthorizer;
use CoreX\Api\Contracts\OperationInvoker;
use CoreX\Api\Contracts\ReplayAuthorizer;
use CoreX\Api\Data\IdempotencyAttempt;
use CoreX\Api\Data\IdempotencyScope;
use CoreX\Api\Data\OperationInvocation;
use CoreX\Api\Data\OperationResult;
use CoreX\Api\Data\StoredOperationOutcome;
use CoreX\Api\Enums\IdempotencyRequirement;
use CoreX\Api\Internal\Cost\BudgetAdmission;
use CoreX\Api\Internal\Cost\BudgetRateLimited;
use CoreX\Api\Internal\Cost\BudgetStoreUnavailable;
use CoreX\Modules\Contracts\ModuleRegistry;
use Illuminate\Database\Connection;
use Override;
use Throwable;

/** @internal Adds tenant-transactional idempotency around the shared operation invoker. */
final class IdempotentOperationInvoker implements OperationInvoker
{
    public function __construct(
        private readonly OperationInvoker $inner,
        /** @var Closure(): IdempotencyStore */
        private readonly Closure $store,
        private readonly Connection $connection,
        private readonly ModuleRegistry $modules,
        private readonly OperationAuthorizer $authorizer,
        private readonly ReplayAuthorizer $replayAuthorizer,
        private readonly ErrorMapper $errors,
        private readonly ?BudgetAdmission $budget = null,
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
            $fresh = StoredOperationOutcome::success($result->data);
            $projected = $this->project($invocation, $fresh, $result->replayed);

            return new OperationResult(
                data: $projected->data,
                replayed: $projected->replayed,
                metadata: $result->metadata,
            );
        }

        if (! in_array($invocation->operation->effectMode, ['tenant_transactional', 'same_transaction_outbox'], true)) {
            throw new ApiError('idempotency_not_supported', 422, 'This operation does not support transactional idempotency.');
        }

        $scope = OperationFingerprint::scope($invocation, $this->connection);
        $fingerprint = OperationFingerprint::request($invocation);
        $handlerFailure = new class
        {
            public ?ApiError $error = null;
        };
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
                    return [StoredOperationOutcome::fromCanonicalJson((string) $attempt->outcomeJson), true];
                }

                $this->throwUnlessOwner($attempt, $invocation->operation->contractHash);

                try {
                    $result = $this->inner->invoke($invocation);
                    $effectCompleted = true;
                    $freshMetadata = $result->metadata;
                } catch (BudgetStoreUnavailable $error) {
                    throw $error;
                } catch (BudgetRateLimited $error) {
                    throw $error;
                } catch (Throwable $error) {
                    $handlerFailure->error = $this->errors->map($error);

                    throw $error;
                }

                $outcome = StoredOperationOutcome::success($result->data);
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
                    throw $outcome->error();
                }

                return $this->project($invocation, $outcome, true);
            }

            if ($effectCompleted && $terminalPersisted) {
                throw new ApiError(
                    errorCode: 'reconciliation_required',
                    status: 409,
                    message: 'The operation outcome must be reconciled before retry.',
                );
            }

            if ($error instanceof BudgetStoreUnavailable || $error instanceof BudgetRateLimited) {
                throw $error;
            }

            throw $this->errors->map($error);
        }

        $projected = $this->project($invocation, $outcome, $replayed);

        if ($replayed && $this->budget !== null) {
            return $this->budget->replay($invocation, $projected);
        }

        return new OperationResult(
            data: $projected->data,
            replayed: $projected->replayed,
            metadata: $freshMetadata,
        );
    }

    private function admitOperation(OperationInvocation $invocation): void
    {
        $operation = $invocation->operation;

        if (! $operation->executionSupported) {
            throw new ApiError('operation_unsupported', 404, 'Operation is not executable.');
        }

        if (! $this->modules->isActive($operation->module, $invocation->context->tenant())) {
            throw new ApiError('module_unavailable', 404, 'Operation not found.');
        }

        if (! $this->authorizer->allows($operation, $invocation->context)) {
            throw new ApiError('forbidden', 403, 'Operation is not permitted.');
        }
    }

    private function admitKey(OperationInvocation $invocation): ?string
    {
        $key = $invocation->idempotencyKey;
        $requirement = $invocation->operation->idempotency;

        if ($requirement === IdempotencyRequirement::Forbidden && $key !== null) {
            throw new ApiError('idempotency_key_forbidden', 422, 'An idempotency key is not allowed for this operation.');
        }

        if ($requirement === IdempotencyRequirement::Required && $key === null) {
            throw new ApiError('idempotency_key_required', 422, 'An idempotency key is required for this operation.');
        }

        if ($key !== null && (strlen($key) < 1 || strlen($key) > 255)) {
            throw new ApiError('idempotency_key_invalid', 422, 'The idempotency key must contain 1-255 bytes.');
        }

        return $key;
    }

    private function throwUnlessOwner(IdempotencyAttempt $attempt, string $contractHash): void
    {
        if ($attempt->ownsLease()) {
            return;
        }

        if (! hash_equals($attempt->contractHash, $contractHash)) {
            throw new ApiError('idempotency_contract_mismatch', 409, 'The stored outcome belongs to a different operation contract.');
        }

        throw match ($attempt->disposition) {
            IdempotencyAttempt::Conflict => new ApiError('idempotency_conflict', 409, 'The idempotency key was used with different input.'),
            IdempotencyAttempt::ContractMismatch => new ApiError('idempotency_contract_mismatch', 409, 'The stored outcome belongs to a different operation contract.'),
            IdempotencyAttempt::InProgress => new ApiError('idempotency_in_progress', 409, 'The operation is already in progress.', retryable: true),
            default => new ApiError('internal_error', 500, 'Operation could not be completed.'),
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
                    return StoredOperationOutcome::fromCanonicalJson((string) $attempt->outcomeJson);
                }

                $this->throwUnlessOwner($attempt, $invocation->operation->contractHash);
                $outcome = StoredOperationOutcome::failure($error);
                $store->fail($attempt, $outcome->canonicalJson());
                $terminalPersisted = true;

                return $outcome;
            });
        } catch (Throwable $persistenceError) {
            if ($terminalPersisted) {
                throw new ApiError(
                    errorCode: 'reconciliation_required',
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
        $authorized = $this->replayAuthorizer->authorize($invocation, $outcome, $replayed);

        if ($authorized->kind === 'error') {
            throw $authorized->error();
        }

        return $authorized->result($replayed);
    }

    private function isPreAdmissionFailure(ApiError $error): bool
    {
        return in_array($error->errorCode, [
            'access_denied',
            'authentication_required',
            'forbidden',
            'module_unavailable',
            'operation_not_found',
            'operation_unsupported',
            'scope_required',
            'validation_failed',
        ], true);
    }
}
