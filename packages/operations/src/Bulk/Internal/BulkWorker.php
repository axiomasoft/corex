<?php

declare(strict_types=1);

namespace CoreX\Operations\Bulk\Internal;

use CoreX\Data\Data\DataScope;
use CoreX\Data\Laravel\JsonDocuments;
use CoreX\Operations\Bulk\Contracts\BulkOperationStore;
use CoreX\Operations\Bulk\Data\BulkOperation;
use CoreX\Operations\Bulk\Enums\BulkOperationStatus;
use Psr\Clock\ClockInterface;
use Throwable;

/** @internal One leased bulk step. Observer failures never change the durable outcome. */
final readonly class BulkWorker
{
    public function __construct(
        private BulkOperationStore $store,
        private TransferBulkExecutor $executor,
        private ClockInterface $clock,
        private string $worker,
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

        if ($claimed->nextAttemptAt > $this->clock->now()) {
            return $claimed;
        }

        $this->observe(observer: $observer, phase: 'started', operation: $claimed, metrics: []);

        try {
            $advanced = $this->executor->advance($claimed);
        } catch (TransferStampDrift) {
            $advanced = $this->failed(operation: $claimed, code: 'contract_drift');
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

    private function failed(BulkOperation $operation, string $code): BulkOperation
    {
        return new BulkOperation(
            definition: $operation->definition,
            transferRef: JsonDocuments::nullable(JsonDocuments::optionalMap($operation->transferRef)),
            status: BulkOperationStatus::Failed,
            terminalStatus: $operation->terminalStatus,
            revision: $operation->revision,
            leaseOwner: $operation->leaseOwner,
            leaseUntil: $operation->leaseUntil,
            cancelRequestedAt: $operation->cancelRequestedAt,
            checkpoint: JsonDocuments::capture(JsonDocuments::map($operation->checkpoint)),
            progress: JsonDocuments::capture(['phase' => 'failed']),
            resultRef: JsonDocuments::nullable(JsonDocuments::optionalMap($operation->resultRef)),
            error: JsonDocuments::nullable(['code' => $code]),
            attempt: $operation->attempt,
            nextAttemptAt: $operation->nextAttemptAt,
            createdAt: $operation->createdAt,
            finishedAt: $operation->finishedAt,
            expiresAt: $operation->expiresAt,
        );
    }
}
