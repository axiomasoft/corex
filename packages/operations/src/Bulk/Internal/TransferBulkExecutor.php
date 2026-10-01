<?php

declare(strict_types=1);

namespace CoreX\Operations\Bulk\Internal;

use CoreX\Data\Contracts\DataScopeCodec;
use CoreX\Data\Data\DataScope;
use CoreX\Data\Laravel\JsonDocuments;
use CoreX\Operations\Bulk\Contracts\BulkOperationStore;
use CoreX\Operations\Bulk\Contracts\BulkTransferFactory;
use CoreX\Operations\Bulk\Data\BulkOperation;
use CoreX\Operations\Bulk\Data\BulkOperationDefinition;
use CoreX\Operations\Bulk\Data\BulkTransferSession;
use CoreX\Operations\Bulk\Enums\BulkOperationStatus;
use CoreX\Transfer\Contracts\ExportRunner;
use CoreX\Transfer\Contracts\TransferRunner;
use CoreX\Transfer\Data\TransferReceipt;
use DateInterval;
use DateTimeImmutable;
use InvalidArgumentException;
use Psr\Clock\ClockInterface;

/** @internal Delegates one bulk batch to public transfer ports. */
final readonly class TransferBulkExecutor
{
    public function __construct(
        private ?BulkTransferFactory $factory,
        private BulkOperationStore $store,
        private ClockInterface $clock,
        private bool $transferInstalled = true,
        private int $retrySeconds = 5,
        private ?DataScopeCodec $scopes = null,
    ) {
        if ($retrySeconds < 1) {
            throw new InvalidArgumentException('Bulk transfer retry delay must be positive.');
        }
    }

    public function submit(BulkOperationDefinition $definition): BulkOperation
    {
        if ($this->transfers($definition)) {
            $this->assertAvailable();
        }

        return $this->store->submit($definition);
    }

    public function advance(BulkOperation $operation): BulkOperation
    {
        $this->assertAvailable();
        $session = $this->factory->create($operation);
        $this->assertStamp($operation, $session->token->restoreEpoch, $session->token->schemaHash, $session->token->mappingHash);

        if ($session->cancellation->isCancelled()) {
            $status = $operation->status === BulkOperationStatus::Cancelling
                ? BulkOperationStatus::Cancelled
                : BulkOperationStatus::Cancelling;

            return $this->with($operation, $status, JsonDocuments::map($operation->checkpoint), [
                'phase' => $status->value,
            ], error: ['code' => 'cancelled']);
        }

        if ($session->runner instanceof ExportRunner) {
            return $this->export($operation, $session->runner, $session);
        }

        $receipt = $session->runner->step(
            $this->scopeOf($operation),
            $session->transfer,
            $session->token,
        );

        return $this->fromReceipt($operation, $receipt, $session->transfer->id);
    }

    private function assertAvailable(): void
    {
        $installed = $this->transferInstalled
            && interface_exists(TransferRunner::class)
            && interface_exists(ExportRunner::class);

        if (! $installed || $this->factory === null) {
            throw new TransferCapabilityUnavailable;
        }
    }

    private function transfers(BulkOperationDefinition $definition): bool
    {
        $kind = JsonDocuments::map($definition->inputRef)['kind'] ?? null;

        return in_array($kind, ['transfer', 'import', 'export'], true);
    }

    private function assertStamp(BulkOperation $operation, string $restoreEpoch, string $schemaHash, string $mappingHash): void
    {
        $stamp = JsonDocuments::map($operation->definition->extensionStamp);
        $expectedRestore = $stamp['restore_epoch'] ?? null;
        $expectedVersion = $stamp['version'] ?? null;
        $expectedRights = $stamp['rights'] ?? null;

        if (! is_string($expectedRestore) || ! is_string($expectedVersion) || ! is_string($expectedRights)) {
            throw new InvalidArgumentException('Transfer bulk extension stamp must carry restore epoch, version and rights.');
        }

        if (! hash_equals($expectedRestore, $restoreEpoch)
            || ! hash_equals(hash('sha256', $expectedVersion), $schemaHash)
            || ! hash_equals(hash('sha256', $expectedRights), $mappingHash)) {
            throw new TransferStampDrift;
        }
    }

    private function export(BulkOperation $operation, ExportRunner $runner, BulkTransferSession $session): BulkOperation
    {
        $manifest = $session->manifest;
        $cursor = $session->cursor;

        if ($manifest === null || $cursor === null) {
            throw new InvalidArgumentException('Export bulk session is incomplete.');
        }

        $previous = JsonDocuments::map($operation->checkpoint)['attempt_id'] ?? null;
        $sealed = JsonDocuments::map($operation->checkpoint)['sealed'] ?? null;

        if (is_string($previous) && $sealed !== true && $previous === $manifest->id) {
            throw new TransferStampDrift;
        }

        $sealedManifest = $runner->seal($this->scopeOf($operation), $operation->definition->id, $manifest, $cursor);
        $runner->emit($this->scopeOf($operation), $sealedManifest, $cursor);

        return $this->with($operation, BulkOperationStatus::Completed, [
            'attempt_id' => $sealedManifest->id,
            'sealed' => true,
            'sequence' => JsonDocuments::map($operation->checkpoint)['sequence'] ?? '0',
        ], [
            'phase' => 'completed',
            'attempt_id' => $sealedManifest->id,
        ], transferRef: ['id' => $session->transfer->id], resultRef: [
            'id' => $sealedManifest->id,
            'media_type' => 'application/vnd.corex.export',
        ]);
    }

    private function fromReceipt(BulkOperation $operation, TransferReceipt $receipt, string $transferId): BulkOperation
    {
        $sequence = $receipt->cursor->ordinal;
        $checkpoint = [
            'sequence' => $sequence,
            'source_hash' => $receipt->cursor->sourceHash,
        ];
        $progress = [
            'phase' => $receipt->state,
            'sequence' => $sequence,
        ];
        $status = match ($receipt->state) {
            'completed' => BulkOperationStatus::Completed,
            'cancelled' => BulkOperationStatus::Cancelled,
            'failed', 'unknown' => BulkOperationStatus::Failed,
            default => BulkOperationStatus::Running,
        };
        $error = $receipt->errorCode === null ? null : ['code' => $receipt->errorCode];

        if ($receipt->state === 'unknown') {
            $error = ['code' => 'bulk_reconciliation_required'];
        }

        $next = $status === BulkOperationStatus::Running
            ? $this->clock->now()->add(new DateInterval("PT{$this->retrySeconds}S"))
            : $operation->nextAttemptAt;

        return $this->with(
            $operation,
            $status,
            $checkpoint,
            $progress,
            $error,
            ['id' => $transferId],
            $status === BulkOperationStatus::Completed ? ['id' => $transferId, 'media_type' => 'application/json'] : null,
            $next,
        );
    }

    /**
     * @param  array<string, mixed>  $checkpoint
     * @param  array<string, mixed>  $progress
     * @param  array<string, mixed>|null  $error
     * @param  array<string, mixed>|null  $transferRef
     * @param  array<string, mixed>|null  $resultRef
     */
    private function with(
        BulkOperation $operation,
        BulkOperationStatus $status,
        array $checkpoint,
        array $progress,
        ?array $error = null,
        ?array $transferRef = null,
        ?array $resultRef = null,
        ?DateTimeImmutable $nextAttemptAt = null,
    ): BulkOperation {
        return new BulkOperation(
            definition: $operation->definition,
            transferRef: JsonDocuments::nullable($transferRef ?? JsonDocuments::optionalMap($operation->transferRef)),
            status: $status,
            terminalStatus: $operation->terminalStatus,
            revision: $operation->revision,
            leaseOwner: $operation->leaseOwner,
            leaseUntil: $operation->leaseUntil,
            cancelRequestedAt: $operation->cancelRequestedAt,
            checkpoint: JsonDocuments::capture($checkpoint),
            progress: JsonDocuments::capture($progress),
            resultRef: JsonDocuments::nullable($resultRef ?? JsonDocuments::optionalMap($operation->resultRef)),
            error: JsonDocuments::nullable($error),
            attempt: $operation->attempt,
            nextAttemptAt: $nextAttemptAt ?? $operation->nextAttemptAt,
            createdAt: $operation->createdAt,
            finishedAt: $operation->finishedAt,
            expiresAt: $operation->expiresAt,
        );
    }

    private function scopeOf(BulkOperation $operation): DataScope
    {
        return ($this->scopes ?? throw new InvalidArgumentException('Transfer bulk execution requires a scope codec.'))->decode($operation->definition->encodedScope);
    }
}
