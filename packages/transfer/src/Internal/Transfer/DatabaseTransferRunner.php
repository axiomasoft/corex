<?php

declare(strict_types=1);

namespace CoreX\Transfer\Internal\Transfer;

use CoreX\Contracts\DataScopeAuthorizer;
use CoreX\Support\Data\DataScope;
use CoreX\Support\Data\WriteToken;
use CoreX\Transfer\Contracts\ImportTarget;
use CoreX\Transfer\Contracts\RecordMapper;
use CoreX\Transfer\Contracts\TransferRunner;
use CoreX\Transfer\Contracts\TransferSource;
use CoreX\Transfer\Data\CanonicalRecord;
use CoreX\Transfer\Data\ConflictRule;
use CoreX\Transfer\Data\MappingSnapshot;
use CoreX\Transfer\Data\SourceCursor;
use CoreX\Transfer\Data\StepToken;
use CoreX\Transfer\Data\TargetResult;
use CoreX\Transfer\Data\TransferLimits;
use CoreX\Transfer\Data\TransferReceipt;
use CoreX\Transfer\Data\TransferRef;
use Illuminate\Database\ConnectionInterface;
use Override;
use Throwable;

final readonly class DatabaseTransferRunner implements TransferRunner
{
    public function __construct(
        private ConnectionInterface $connection,
        private TransferSource $source,
        private RecordMapper $mapper,
        private ImportTarget $target,
        private MappingSnapshot $mapping,
        private ConflictRule $conflictRule,
        private TransferLimits $limits,
        private DataScopeAuthorizer $authorizer,
    ) {}

    #[Override]
    public function step(DataScope $scope, TransferRef $transfer, StepToken $token): TransferReceipt
    {
        return $this->connection->transaction(function () use ($scope, $transfer, $token): TransferReceipt {
            $job = $this->connection->table('corex_transfer_jobs')->where('id', $transfer->id)->lockForUpdate()->first();

            if ($job === null) {
                throw new TransferUnavailable('Transfer job is not available.');
            }

            $this->assertFence(job: $job, scope: $scope, token: $token);
            $cursor = $this->cursor(job: $job);
            $manifest = $this->source->describe(scope: $scope);

            if ($manifest->sha256 !== $token->sourceHash || $manifest->schemaHash !== $token->schemaHash) {
                throw new TransferUnavailable('Source changed; transfer resume is refused.');
            }

            $batch = $this->source->read(source: $manifest, after: $cursor, limits: $this->limits);
            $gaps = [];
            $stats = $this->stats(job: $job);
            $dryRun = $job->mode === 'dryRun';

            foreach ($batch->rows as $row) {
                $mapped = $this->mapper->map(row: $row, mapping: $this->mapping);

                if ($mapped->record === null) {
                    $stats['invalid'] = ($stats['invalid'] ?? 0) + 1;
                    $gaps[] = $row->ordinal;

                    if (! $dryRun) {
                        $this->saveRow(jobId: $transfer->id, ordinal: $row->ordinal, result: 'invalid', payloadHash: str_repeat('0', 64), epoch: $token->epoch, errorCode: $mapped->errorCode);
                    }

                    continue;
                }

                if ($dryRun) {
                    $decision = $this->target->preview(scope: $scope, record: $mapped->record, rule: $this->conflictRule);
                    $stats[$decision->status] = ($stats[$decision->status] ?? 0) + 1;

                    continue;
                }

                try {
                    $result = $this->connection->transaction(function () use ($scope, $transfer, $token, $row, $mapped): TargetResult {
                        $writeToken = $this->writeToken(record: $mapped->record);
                        $existing = $this->lockReference(jobId: $transfer->id, record: $mapped->record);
                        $result = $existing ? $this->target->lookup(scope: $scope, token: $writeToken) : $this->target->apply(scope: $scope, record: $mapped->record, rule: $this->conflictRule, token: $writeToken);
                        $this->storeReference(record: $mapped->record, result: $result);
                        $this->recordResult(jobId: $transfer->id, ordinal: $row->ordinal, result: $result, payloadHash: $mapped->record->payloadHash, epoch: $token->epoch);

                        return $result;
                    });
                } catch (Throwable) {
                    $stats['invalid'] = ($stats['invalid'] ?? 0) + 1;
                    $stats['errors'] = ($stats['errors'] ?? 0) + 1;
                    $this->saveRow(jobId: $transfer->id, ordinal: $row->ordinal, result: 'invalid', payloadHash: $mapped->record->payloadHash, epoch: $token->epoch, errorCode: 'rowFailed');

                    continue;
                }

                $stats[$result->status] = ($stats[$result->status] ?? 0) + 1;

                if ($result->status === 'unknown') {
                    $gaps[] = $row->ordinal;
                }
            }

            $next = $gaps === [] ? $batch->next : $cursor;
            $sequence = (string) ((int) $token->checkpointSequence + 1);
            $state = $gaps !== [] ? 'unknown' : ($batch->remaining ? 'running' : 'completed');

            if (! $dryRun) {
                $changed = $this->connection->table('corex_transfer_jobs')
                    ->where('id', $transfer->id)
                    ->where('checkpoint_seq', $token->checkpointSequence)
                    ->where('claim_epoch', $token->epoch)
                    ->update([
                        'checkpoint_seq' => $sequence,
                        'cursor' => json_encode(['ordinal' => $next->ordinal, 'boundaryByteOffset' => $next->boundaryByteOffset], JSON_THROW_ON_ERROR),
                        'stats' => json_encode($stats, JSON_THROW_ON_ERROR),
                        'state' => $state,
                        'updated_at' => now(),
                    ]);

                if ($changed !== 1) {
                    throw new TransferUnavailable('Transfer checkpoint lease was superseded.');
                }
            }

            return new TransferReceipt(state: $state, cursor: $next, checkpointSequence: $sequence, stats: $stats, gaps: $gaps, errorCode: $gaps === [] ? null : 'unknownTarget');
        }, attempts: 3);
    }

    private function assertFence(object $job, DataScope $scope, StepToken $token): void
    {
        if ($job->checkpoint_seq !== $token->checkpointSequence || $job->claim_epoch !== $token->epoch || $job->source_hash !== $token->sourceHash || $job->mapping_hash !== $token->mappingHash || $job->schema_hash !== $token->schemaHash || $job->restore_epoch !== $token->restoreEpoch) {
            throw new TransferUnavailable('Transfer checkpoint token does not match the current job.');
        }

        if (! $this->authorizer->authorize(scope: $scope, entity: '*', subjectId: $job->id, fields: [], action: 'transfer.run')->allowed) {
            throw new TransferUnavailable('Transfer access is denied by the current policy.');
        }
    }

    private function cursor(object $job): SourceCursor
    {
        $value = json_decode($job->cursor, true, 16, JSON_THROW_ON_ERROR);

        if (! is_array($value) || ! isset($value['ordinal']) || ! array_key_exists('boundaryByteOffset', $value)) {
            throw new TransferUnavailable('Transfer checkpoint cursor is invalid.');
        }

        return new SourceCursor(ordinal: $value['ordinal'], boundaryByteOffset: $value['boundaryByteOffset'], sourceHash: $job->source_hash);
    }

    /** @return array<string, int> */
    private function stats(object $job): array
    {
        $value = json_decode($job->stats, true, 16, JSON_THROW_ON_ERROR);

        if (! is_array($value) || ($value !== [] && array_is_list($value))) {
            throw new TransferUnavailable('Transfer statistics are invalid.');
        }

        return array_map(static fn (mixed $count): int => is_int($count) && $count >= 0 ? $count : 0, $value);
    }

    private function recordResult(string $jobId, string $ordinal, TargetResult $result, string $payloadHash, string $epoch): void
    {
        $this->saveRow(jobId: $jobId, ordinal: $ordinal, result: $result->status, payloadHash: $payloadHash, epoch: $epoch, errorCode: $result->errorCode, providerTask: $result->providerTask);
    }

    private function writeToken(CanonicalRecord $record): WriteToken
    {
        return new WriteToken(
            operationKey: hash('sha256', implode('|', [$record->entity, $record->externalSystem, $record->externalId, $record->sourceRevision])),
            payloadHash: $record->payloadHash,
            expectedRecordVersion: 'absent',
        );
    }

    private function lockReference(string $jobId, CanonicalRecord $record): bool
    {
        $inserted = $this->connection->table('corex_transfer_refs')->insertOrIgnore([
            'id' => (string) str()->uuid(), 'job_id' => $jobId, 'entity' => $record->entity,
            'external_system' => $record->externalSystem, 'external_id' => $record->externalId,
            'source_revision' => $record->sourceRevision, 'source_updated_at' => $record->sourceUpdatedAt,
            'payload_hash' => $record->payloadHash, 'state' => 'running', 'created_at' => now(), 'updated_at' => now(),
        ]);

        if ($inserted === 1) {
            return false;
        }

        $existing = $this->connection->table('corex_transfer_refs')
            ->where('entity', $record->entity)
            ->where('external_system', $record->externalSystem)
            ->where('external_id', $record->externalId)
            ->lockForUpdate()
            ->first();

        if ($existing === null) {
            throw new TransferUnavailable('Transfer reference disappeared during deduplication.');
        }

        return $existing->source_revision === $record->sourceRevision && $existing->payload_hash === $record->payloadHash;
    }

    private function storeReference(CanonicalRecord $record, TargetResult $result): void
    {
        $this->connection->table('corex_transfer_refs')
            ->where('entity', $record->entity)->where('external_system', $record->externalSystem)->where('external_id', $record->externalId)
            ->update(['target_id' => $result->target?->id, 'payload_hash' => $record->payloadHash, 'source_revision' => $record->sourceRevision, 'source_updated_at' => $record->sourceUpdatedAt, 'state' => $result->status, 'result' => json_encode(['status' => $result->status], JSON_THROW_ON_ERROR), 'updated_at' => now()]);
    }

    private function saveRow(string $jobId, string $ordinal, string $result, string $payloadHash, string $epoch, ?string $errorCode, ?string $providerTask = null): void
    {
        $this->connection->table('corex_transfer_rows')->updateOrInsert(
            attributes: ['job_id' => $jobId, 'ordinal' => $ordinal],
            values: [
                'id' => (string) str()->uuid(),
                'external_identity_hash' => hash('sha256', $jobId.'|'.$ordinal),
                'operation_key' => hash('sha256', $jobId.'|'.$ordinal),
                'payload_hash' => $payloadHash,
                'result' => $result,
                'error' => $errorCode === null ? null : json_encode(['code' => $errorCode], JSON_THROW_ON_ERROR),
                'provider_task' => $providerTask,
                'epoch' => $epoch,
            ],
        );
    }
}
