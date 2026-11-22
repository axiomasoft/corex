<?php

declare(strict_types=1);

namespace CoreX\Operations\Bulk\Internal;

use Closure;
use CoreX\Context\Data\OperationKey;
use CoreX\Data\Data\DataScope;
use CoreX\Data\Laravel\JsonDocuments;
use CoreX\Operations\Bulk\Contracts\BulkExecutor;
use CoreX\Operations\Bulk\Contracts\BulkOperationStore;
use CoreX\Operations\Bulk\Data\BulkOperation;
use CoreX\Operations\Bulk\Data\BulkStep;
use CoreX\Operations\Bulk\Data\BulkStepResult;
use CoreX\Operations\Bulk\Enums\BulkOperationStatus;
use CoreX\Operations\Bulk\Enums\BulkStepStatus;
use CoreX\Operations\Bulk\Laravel\BulkReferences;
use CoreX\Operations\Errors\Enums\OperationsErrorCode;
use CoreX\Operations\Errors\Exceptions\ApiError;
use CoreX\Operations\Validation\Laravel\JsonCanonical;
use DateInterval;
use Packman\Attributes\Internal;
use Psr\Clock\ClockInterface;
use Throwable;
use UnexpectedValueException;

/** @internal One leased bulk step. Observer failures never change the durable outcome. */
#[Internal]
final readonly class BulkWorker
{
    /** @param Closure(): BulkExecutor $executor */
    public function __construct(
        private BulkOperationStore $store,
        private Closure $executor,
        private ClockInterface $clock,
        private string $worker,
        private int $retrySeconds = 5,
        private int $maxRecoveryAttempts = 5,
    ) {}

    /**
     * @param  null|callable(string, array<string, int|string>): void  $observer
     */
    public function workOnce(DataScope $scope, ?callable $observer = null): ?BulkOperation
    {
        $claimed = $this->store->claim(scope: $scope, worker: $this->worker);

        if ($claimed === null) {
            return null;
        }

        $this->observe(observer: $observer, phase: 'started', operation: $claimed, metrics: []);
        $now = $this->clock->now();
        $definition = $claimed->definition;

        if (! $this->store->ownsClaim(scope: $scope, operation: $claimed) || $claimed->leaseUntil === null
            || $now->add(new DateInterval("PT{$definition->timeLimitSeconds}S")) >= $claimed->leaseUntil) {
            return $claimed;
        }

        try {
            $result = $claimed->recoveryAttempts > $this->maxRecoveryAttempts
                ? new BulkStepResult(status: BulkStepStatus::Failed, nextCheckpoint: $claimed->checkpoint, counters: [], errorCode: 'bulk_reconciliation_required')
                : $this->step(scope: $scope, operation: $claimed);
            $advanced = $this->advance(operation: $claimed, outcome: $result);
        } catch (ApiError $error) {
            if ($error->errorCode !== OperationsErrorCode::IdempotencyContractMismatch->value) {
                throw $error;
            }
            $advanced = $this->advance(operation: $claimed, outcome: new BulkStepResult(
                status: BulkStepStatus::Failed, nextCheckpoint: $claimed->checkpoint, counters: [],
                errorCode: $claimed->recoveryAttempts > 1 ? 'bulk_reconciliation_required' : 'contract_drift',
            ));
        } catch (Throwable $exception) {
            $this->observe(observer: $observer, phase: 'failed', operation: $claimed, metrics: ['safe' => 1]);

            throw $exception;
        }

        $saved = $this->store->save(operation: $advanced, leaseOwner: $this->worker, revision: $claimed->revision);

        if (! $saved) {
            return $claimed;
        }

        $phase = $advanced->status === BulkOperationStatus::Failed ? 'failed' : 'completed';
        $this->observe(observer: $observer, phase: $phase, operation: $advanced, metrics: $this->metrics($advanced));

        return $advanced;
    }

    /**
     * @param  null|callable(string, array<string, int|string>): void  $observer
     * @param  array<string, int|string>  $metrics
     */
    private function observe(?callable $observer, string $phase, BulkOperation $operation, array $metrics): void
    {
        if ($observer === null) {
            return;
        }

        try {
            $observer($phase, [
                'operation_id' => $operation->definition->id,
                'correlation_id' => $operation->definition->submissionId,
            ] + $metrics);
        } catch (Throwable) {
            // Observer output is diagnostic. It cannot roll back a committed step.
        }
    }

    /** @return array<string, int|string> */
    private function metrics(BulkOperation $operation): array
    {
        $metrics = [];

        foreach (JsonDocuments::map($operation->progress) as $key => $value) {
            if (is_int($value) || is_string($value)) {
                $metrics[$key] = $value;
            }
        }

        return $metrics;
    }

    private function step(DataScope $scope, BulkOperation $operation): BulkStepResult
    {
        $definition = $operation->definition;
        $token = hash('sha256', JsonCanonical::encode([
            'corex.bulk.step/1', $definition->id, $definition->encodedScope, $definition->contractHash,
            JsonDocuments::map($definition->inputRef), JsonDocuments::map($definition->extensionStamp),
            $definition->rowLimit, $definition->timeLimitSeconds, $operation->checkpoint->sequence ?? 0,
        ]));
        $context = $definition->context(scope: $scope, key: new OperationKey(id: $definition->id, attempt: $operation->attempt, effectKey: $token, deadline: $operation->leaseUntil));

        return ($this->executor)()->step($context, new BulkStep(
            token: $token, checkpoint: $operation->checkpoint, rowLimit: $definition->rowLimit, timeLimitSeconds: $definition->timeLimitSeconds, cancelRequested: $operation->cancelRequestedAt !== null,
        ));
    }

    private function advance(BulkOperation $operation, BulkStepResult $outcome): BulkOperation
    {
        $owners = array_map(static fn ($target): string => $target->package, BulkReferences::targets($operation->definition));
        foreach ([$outcome->resultRef, $outcome->failurePageRef] as $ref) {
            if ($ref !== null && ! in_array($ref->owner, $owners, true)) {
                throw new UnexpectedValueException('Bulk step returned an undeclared owner reference.');
            }
        }

        $checkpoint = $outcome->nextCheckpoint ?? $operation->checkpoint;
        $before = $operation->checkpoint->sequence ?? 0;
        $after = $checkpoint->sequence ?? 0;

        if ($after < $before || $after > $before + 1
            || ($after === $before && $checkpoint?->ownerState->canonicalJson !== $operation->checkpoint?->ownerState->canonicalJson)) {
            throw new UnexpectedValueException('Bulk step returned an invalid continuation.');
        }

        $status = match ($outcome->status) {
            BulkStepStatus::Completed => BulkOperationStatus::Completed,
            BulkStepStatus::Failed => BulkOperationStatus::Failed,
            BulkStepStatus::Cancelled => BulkOperationStatus::Cancelled,
            default => $operation->status,
        };
        $progress = $outcome->counters === [] ? JsonDocuments::map($operation->progress) : $outcome->counters;
        $progress['phase'] = $outcome->status->value;

        return new BulkOperation(
            definition: $operation->definition,
            status: $status,
            terminalStatus: $operation->terminalStatus,
            revision: $operation->revision,
            leaseOwner: $operation->leaseOwner,
            leaseUntil: $operation->leaseUntil,
            cancelRequestedAt: $operation->cancelRequestedAt,
            checkpoint: $checkpoint,
            progress: JsonDocuments::capture($progress),
            resultRef: $operation->resultRef,
            error: JsonDocuments::nullable($outcome->errorCode === null ? null : ['code' => $outcome->errorCode]),
            attempt: $operation->attempt,
            nextAttemptAt: $status->isTerminal()
                ? $operation->nextAttemptAt
                : $this->clock->now()->add(new DateInterval("PT{$this->retrySeconds}S")),
            createdAt: $operation->createdAt,
            finishedAt: $operation->finishedAt,
            expiresAt: $operation->expiresAt,
            ownerResultRef: $outcome->resultRef ?? $operation->ownerResultRef,
            failurePageRef: $outcome->failurePageRef ?? $operation->failurePageRef,
            recoveryAttempts: $after > $before ? 0 : $operation->recoveryAttempts,
        );
    }
}
