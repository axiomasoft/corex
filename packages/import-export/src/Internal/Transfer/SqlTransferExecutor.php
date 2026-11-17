<?php

declare(strict_types=1);

namespace CoreX\ImportExport\Internal\Transfer;

use CoreX\Data\Contracts\DataScopeAuthorizer;
use CoreX\Data\Data\DataScope;
use CoreX\Data\Data\WriteToken;
use CoreX\ImportExport\Contracts\CancellationSignal;
use CoreX\ImportExport\Contracts\RecordMapper;
use CoreX\ImportExport\Contracts\TransferRunner;
use CoreX\ImportExport\Contracts\TransferSink;
use CoreX\ImportExport\Contracts\TransferSource;
use CoreX\ImportExport\Data\CanonicalRecord;
use CoreX\ImportExport\Data\ConflictRule;
use CoreX\ImportExport\Data\MappingSnapshot;
use CoreX\ImportExport\Data\RowBatch;
use CoreX\ImportExport\Data\SourceCursor;
use CoreX\ImportExport\Data\SourceManifest;
use CoreX\ImportExport\Data\StepToken;
use CoreX\ImportExport\Data\TargetResult;
use CoreX\ImportExport\Data\TransferLimits;
use CoreX\ImportExport\Data\TransferReceipt;
use CoreX\ImportExport\Data\TransferRef;
use CoreX\ImportExport\Exceptions\ImportUnavailable;
use CoreX\ImportExport\Internal\SessionIdentity;
use CoreX\ImportExport\Internal\SessionRuntime;
use CoreX\ImportExport\Internal\Value;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Str;
use Override;
use Psr\Clock\ClockInterface;
use Throwable;

final readonly class SqlTransferExecutor implements TransferRunner
{
    public function __construct(
        private ConnectionInterface $connection,
        private TransferSource $source,
        private RecordMapper $mapper,
        private TransferSink $target,
        private MappingSnapshot $mapping,
        private ConflictRule $conflictRule,
        private TransferLimits $limits,
        private DataScopeAuthorizer $authorizer,
        private ClockInterface $clock,
        private CancellationSignal $cancellation = new NeverCancelled,
        private ?ExecutionGuard $guard = null,
        private ?SessionRuntime $runtime = null,
    ) {}

    #[Override]
    public function step(DataScope $scope, TransferRef $transfer, StepToken $token): TransferReceipt
    {
        $this->runtime?->assertCurrent($this->connection, $scope);
        $this->runtime?->assertTransfer($transfer->id);

        try {
            return $this->connection->transaction(function () use ($scope, $transfer, $token): TransferReceipt {
                $job = $this->connection->table('corex_transfer_jobs')->where('id', $transfer->id)->lockForUpdate()->first();

                if ($job === null) {
                    throw new ImportUnavailable('Transfer job is not available.');
                }

                $this->assertFence(job: $job, scope: $scope, token: $token);
                $cursor = $this->cursor(job: $job);
                $manifest = $this->source->describe(scope: $scope);

                if ($manifest->sha256 !== $token->sourceHash || $manifest->schemaHash !== $token->schemaHash) {
                    throw new ImportUnavailable('Source changed; transfer resume is refused.');
                }

                $gaps = [];
                $stats = $this->stats(job: $job);
                $dryRun = $job->mode === 'dryRun';
                $guard = $this->guard ?? ExecutionGuard::production(limits: $this->limits);

                if ($this->cancellation->isCancelled()) {
                    return $this->cancelled(jobId: $transfer->id, cursor: $cursor, checkpointSequence: $token->checkpointSequence, stats: $stats, dryRun: $dryRun);
                }

                $guard->assertWithin(cursor: $cursor, checkpointSequence: $token->checkpointSequence, stats: $stats);
                $batch = $this->source->read(source: $manifest, after: $cursor, limits: $this->limits);
                $guard->assertWithin(cursor: $cursor, checkpointSequence: $token->checkpointSequence, stats: $stats);
                $this->assertSourceBounds(manifest: $manifest, batch: $batch);

                foreach ($batch->rows as $row) {
                    $guard->assertWithin(cursor: $cursor, checkpointSequence: $token->checkpointSequence, stats: $stats);
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

                    $record = $mapped->record;

                    try {
                        $result = $this->connection->transaction(function () use ($scope, $transfer, $token, $row, $record): TargetResult {
                            $writeToken = $this->writeToken(record: $record);
                            $existing = $this->lockReference(jobId: $transfer->id, record: $record);
                            $result = $existing ? $this->target->lookup(scope: $scope, token: $writeToken) : $this->target->apply(scope: $scope, record: $record, rule: $this->conflictRule, token: $writeToken);
                            $this->storeReference(record: $record, result: $result);
                            $this->recordResult(jobId: $transfer->id, ordinal: $row->ordinal, result: $result, payloadHash: $record->payloadHash, epoch: $token->epoch);

                            return $result;
                        });
                    } catch (TransferLimitReached $reached) {
                        throw $reached;
                    } catch (Throwable) {
                        $stats['invalid'] = ($stats['invalid'] ?? 0) + 1;
                        $stats['errors'] = ($stats['errors'] ?? 0) + 1;
                        $this->saveRow(jobId: $transfer->id, ordinal: $row->ordinal, result: 'invalid', payloadHash: $record->payloadHash, epoch: $token->epoch, errorCode: 'rowFailed');

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
                            'updated_at' => $this->clock->now(),
                        ]);

                    if ($changed !== 1) {
                        throw new ImportUnavailable('Transfer checkpoint lease was superseded.');
                    }
                }

                return new TransferReceipt(state: $state, cursor: $next, checkpointSequence: $sequence, stats: $stats, gaps: $gaps, errorCode: $gaps === [] ? null : 'unknownTarget');
            }, attempts: 3);
        } catch (TransferLimitReached $reached) {
            return new TransferReceipt(
                state: 'running',
                cursor: $reached->cursor,
                checkpointSequence: $reached->checkpointSequence,
                stats: $reached->stats,
                gaps: [],
                errorCode: $reached->errorCode,
            );
        }
    }

    /**
     * @param  array<string, int>  $stats
     */
    private function cancelled(string $jobId, SourceCursor $cursor, string $checkpointSequence, array $stats, bool $dryRun): TransferReceipt
    {
        if (! $dryRun) {
            $this->connection->table('corex_transfer_jobs')->where('id', $jobId)->update([
                'state' => 'cancelled',
                'updated_at' => $this->clock->now(),
            ]);
        }

        return new TransferReceipt(
            state: 'cancelled',
            cursor: $cursor,
            checkpointSequence: $checkpointSequence,
            stats: $stats,
            gaps: [],
            errorCode: null,
        );
    }

    private function assertSourceBounds(SourceManifest $manifest, RowBatch $batch): void
    {
        if (count($batch->rows) > $this->limits->batchRows || $manifest->length > $this->limits->maxFileBytes) {
            throw new ImportUnavailable('Source batch exceeds the qualified limits.');
        }

        foreach ($batch->rows as $row) {
            $encoded = json_encode($row->values, JSON_THROW_ON_ERROR);

            if (strlen($encoded) > $this->limits->maxRecordBytes) {
                throw new ImportUnavailable('Source record exceeds the qualified limits.');
            }
        }
    }

    private function assertFence(object $job, DataScope $scope, StepToken $token): void
    {
        SessionIdentity::assertScope($job, $scope);

        if (in_array(Value::field($job, 'state'), ['completed', 'cancelled', 'failed'], true)) {
            throw new ImportUnavailable('Terminal transfers cannot execute another step.');
        }

        if (Value::text(Value::field(row: $job, name: 'checkpoint_seq')) !== $token->checkpointSequence || Value::text(Value::field(row: $job, name: 'claim_epoch')) !== $token->epoch || Value::field(row: $job, name: 'source_hash') !== $token->sourceHash || Value::field(row: $job, name: 'mapping_hash') !== $token->mappingHash || Value::field(row: $job, name: 'schema_hash') !== $token->schemaHash || Value::field(row: $job, name: 'restore_epoch') !== $token->restoreEpoch || ! hash_equals($this->mapping->sha256, $token->mappingHash) || ! hash_equals($this->mapping->schemaHash, $token->schemaHash)) {
            throw new ImportUnavailable('Transfer checkpoint token does not match the current job.');
        }

        if (! $this->authorizer->authorize(scope: $scope, entity: '*', subjectId: Value::text(Value::field(row: $job, name: 'id')), fields: [], action: 'transfer.run')->allowed) {
            throw new ImportUnavailable('Transfer access is denied by the current policy.');
        }
    }

    private function cursor(object $job): SourceCursor
    {
        $value = json_decode(Value::text(Value::field(row: $job, name: 'cursor')), true, 16, JSON_THROW_ON_ERROR);

        if (! is_array($value) || ! isset($value['ordinal']) || ! array_key_exists('boundaryByteOffset', $value)) {
            throw new ImportUnavailable('Transfer checkpoint cursor is invalid.');
        }

        return new SourceCursor(ordinal: Value::text($value['ordinal']), boundaryByteOffset: Value::nullableText($value['boundaryByteOffset']), sourceHash: Value::text(Value::field(row: $job, name: 'source_hash')));
    }

    /** @return array<string, int> */
    private function stats(object $job): array
    {
        $value = json_decode(Value::text(Value::field(row: $job, name: 'stats')), true, 16, JSON_THROW_ON_ERROR);

        if (! is_array($value) || ($value !== [] && array_is_list($value))) {
            throw new ImportUnavailable('Transfer statistics are invalid.');
        }

        return array_map(static fn (mixed $count): int => is_int($count) && $count >= 0 ? $count : 0, Value::map($value));
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
            'id' => Value::text(Str::uuid()), 'job_id' => $jobId, 'entity' => $record->entity,
            'external_system' => $record->externalSystem, 'external_id' => $record->externalId,
            'source_revision' => $record->sourceRevision, 'source_updated_at' => $record->sourceUpdatedAt,
            'payload_hash' => $record->payloadHash, 'state' => 'running', 'created_at' => $this->clock->now(), 'updated_at' => $this->clock->now(),
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
            throw new ImportUnavailable('Transfer reference disappeared during deduplication.');
        }

        return $existing->source_revision === $record->sourceRevision && $existing->payload_hash === $record->payloadHash;
    }

    private function storeReference(CanonicalRecord $record, TargetResult $result): void
    {
        $this->connection->table('corex_transfer_refs')
            ->where('entity', $record->entity)->where('external_system', $record->externalSystem)->where('external_id', $record->externalId)
            ->update(['target_id' => $result->target?->id, 'payload_hash' => $record->payloadHash, 'source_revision' => $record->sourceRevision, 'source_updated_at' => $record->sourceUpdatedAt, 'state' => $result->status, 'result' => json_encode(['status' => $result->status], JSON_THROW_ON_ERROR), 'updated_at' => $this->clock->now()]);
    }

    private function saveRow(string $jobId, string $ordinal, string $result, string $payloadHash, string $epoch, ?string $errorCode, ?string $providerTask = null): void
    {
        $this->connection->table('corex_transfer_rows')->updateOrInsert(
            attributes: ['job_id' => $jobId, 'ordinal' => $ordinal],
            values: [
                'id' => Value::text(Str::uuid()),
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
