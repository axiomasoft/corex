<?php

declare(strict_types=1);

namespace CoreX\ImportExport\Internal\Transfer;

use CoreX\Context\Data\AdmissionTicket;
use CoreX\Context\Enums\ProviderObservationKind;
use CoreX\Data\Contracts\DataScopeAuthorizer;
use CoreX\Data\Data\DataScope;
use CoreX\Data\Data\FieldSelection;
use CoreX\Data\Data\RecordRef;
use CoreX\Data\Data\WriteToken;
use CoreX\Data\Enums\FieldUse;
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
use CoreX\ImportExport\Internal\TransferReferences;
use CoreX\ImportExport\Internal\Value;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Str;
use Override;
use Packman\Attributes\Internal;
use Psr\Clock\ClockInterface;

#[Internal]
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
        private ?TransferReferences $references = null,
    ) {}

    #[Override]
    public function step(DataScope $scope, TransferRef $transfer, StepToken $token): TransferReceipt
    {
        $this->runtime?->assertCurrent(connection: $this->connection, scope: $scope);
        $this->runtime?->assertTransfer($transfer->id);

        $guard = $this->guard ?? ExecutionGuard::production($this->limits);

        try {
            $prepared = $this->prepareStep(scope: $scope, transfer: $transfer, token: $token, guard: $guard);

            if ($prepared instanceof TransferReceipt) {
                return $prepared;
            }

            return $this->connection->transaction(function () use ($scope, $transfer, $token, $guard, $prepared): TransferReceipt {
                $this->runtime?->assertCurrent(connection: $this->connection, scope: $scope);
                $job = $this->connection->table('corex_transfer_jobs')->where('id', $transfer->id)->lockForUpdate()->first();

                if ($job === null) {
                    throw new ImportUnavailable('Transfer job is not available.');
                }

                $this->assertAuthority(job: $job, scope: $scope);
                $receipt = $this->storedReceipt(transfer: $transfer, token: $token);

                if ($receipt !== null) {
                    return $receipt;
                }
                $this->assertFence(job: $job, scope: $scope, token: $token);
                $this->references?->enlist(scope: $scope, connection: $this->connection, stamp: $this->mapping->definition);
                $cursor = $this->cursor(job: $job);
                $manifest = $this->currentManifest(job: $job, scope: $scope, token: $token);

                $gaps = [];
                $stats = $this->stats(job: $job);
                $dryRun = $job->mode === 'dryRun';

                if ($token->stepKey === null && $this->cancellation->isCancelled()) {
                    return $this->cancelled(jobId: $transfer->id, cursor: $cursor, checkpointSequence: $token->checkpointSequence, stats: $stats, dryRun: $dryRun);
                }

                $guard->assertWithin(cursor: $cursor, checkpointSequence: $token->checkpointSequence, stats: $stats);
                $batch = $prepared ?? $this->source->read(source: $manifest, after: $cursor, limits: $this->limits);
                $guard->assertWithin(cursor: $cursor, checkpointSequence: $token->checkpointSequence, stats: $stats);
                $this->assertSourceBounds(manifest: $manifest, batch: $batch);
                $this->verifyBatch(transfer: $transfer, token: $token, batch: $batch, cursor: $cursor, manifest: $manifest);

                foreach ($batch->rows as $row) {
                    $guard->assertWithin(cursor: $cursor, checkpointSequence: $token->checkpointSequence, stats: $stats);
                    $mapped = $this->mapper->map(row: $row, mapping: $this->mapping);

                    if ($mapped->record === null) {
                        $stats['invalid'] = ($stats['invalid'] ?? 0) + 1;

                        if (! $dryRun) {
                            $this->saveRow(jobId: $transfer->id, ordinal: $row->ordinal, result: 'invalid', payloadHash: str_repeat('0', 64), epoch: $token->epoch, errorCode: $mapped->errorCode, path: $mapped->path);
                        }

                        continue;
                    }

                    if ($dryRun) {
                        $decision = $this->target->preview(scope: $scope, record: $mapped->record, rule: $this->conflictRule);
                        $stats[$decision->status] = ($stats[$decision->status] ?? 0) + 1;

                        continue;
                    }

                    $record = $mapped->record;

                    $writeToken = $this->writeToken(record: $record, transfer: $transfer, step: $token, ordinal: $row->ordinal);
                    $existing = $this->lockReference(scope: $scope, jobId: $transfer->id, record: $record);
                    $result = $token->stepKey === null && $this->references === null
                        ? ($existing ? $this->target->lookup(scope: $scope, token: $writeToken) : $this->target->apply(scope: $scope, record: $record, rule: $this->conflictRule, token: $writeToken))
                        : $this->reconcileRow(scope: $scope, transfer: $transfer, record: $record, token: $writeToken);

                    if ($result->status === 'absent-with-proof' && $this->cancellation->isCancelled()) {
                        $cancelled = $this->cancelled(jobId: $transfer->id, cursor: $cursor, checkpointSequence: $token->checkpointSequence, stats: $stats, dryRun: $dryRun);
                        $this->saveReceipt(transfer: $transfer, token: $token, receipt: $cancelled);

                        return $cancelled;
                    }

                    if (in_array($result->status, ['unknown', 'absent-with-proof'], true)) {
                        $this->storeReference(scope: $scope, record: $record, result: $result);
                        $this->recordResult(jobId: $transfer->id, ordinal: $row->ordinal, result: $result, payloadHash: $record->payloadHash, epoch: $token->epoch, key: $writeToken->operationKey);
                        $gaps[] = $row->ordinal;

                        break;
                    }

                    $this->storeReference(scope: $scope, record: $record, result: $result);
                    $this->recordResult(jobId: $transfer->id, ordinal: $row->ordinal, result: $result, payloadHash: $record->payloadHash, epoch: $token->epoch, key: $writeToken->operationKey);
                    $stats[$result->status] = ($stats[$result->status] ?? 0) + 1;
                }

                if ($gaps !== [] && $token->stepKey !== null) {
                    return new TransferReceipt(state: 'unknown', cursor: $cursor, checkpointSequence: $token->checkpointSequence, stats: $this->stats($job), gaps: $gaps, errorCode: 'unknownTarget');
                }

                $guard->assertWithin($cursor, $token->checkpointSequence, $this->stats($job));
                $next = $gaps === [] ? $batch->next : $cursor;
                $sequence = (string) ((int) $token->checkpointSequence + 1);
                $state = $gaps !== [] ? 'unknown' : ($batch->remaining ? 'running' : 'completed');

                if (! $dryRun || $token->stepKey !== null) {
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

                $receipt = new TransferReceipt(state: $state, cursor: $next, checkpointSequence: $sequence, stats: $stats, gaps: $gaps, errorCode: $gaps === [] ? null : 'unknownTarget');
                $this->saveReceipt(transfer: $transfer, token: $token, receipt: $receipt);

                return $receipt;
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

    private function prepareStep(DataScope $scope, TransferRef $transfer, StepToken $token, ExecutionGuard $guard): TransferReceipt|RowBatch|null
    {
        if ($token->stepKey === null) {
            return null;
        }

        return $this->connection->transaction(function () use ($scope, $transfer, $token, $guard): TransferReceipt|RowBatch {
            $this->runtime?->assertCurrent(connection: $this->connection, scope: $scope);
            $job = $this->connection->table('corex_transfer_jobs')->where('id', $transfer->id)->lockForUpdate()->first()
                ?? throw new ImportUnavailable('Transfer job is not available.');
            $this->assertAuthority(job: $job, scope: $scope);
            $receipt = $this->storedReceipt(transfer: $transfer, token: $token);

            if ($receipt !== null) {
                return $receipt;
            }

            $this->assertFence(job: $job, scope: $scope, token: $token);
            $this->references?->enlist(scope: $scope, connection: $this->connection, stamp: $this->mapping->definition);
            $this->connection->table('corex_transfer_steps')->insertOrIgnore([
                'job_id' => $transfer->id, 'step_key' => $token->stepKey, 'digest' => $this->stepDigest($token),
            ]);
            $manifest = $this->currentManifest(job: $job, scope: $scope, token: $token);
            $cursor = $this->cursor($job);
            $guard->assertWithin(cursor: $cursor, checkpointSequence: $token->checkpointSequence, stats: $this->stats($job));
            $batch = $this->source->read(source: $manifest, after: $cursor, limits: $this->limits);
            $guard->assertWithin(cursor: $cursor, checkpointSequence: $token->checkpointSequence, stats: $this->stats($job));
            $this->assertSourceBounds(manifest: $manifest, batch: $batch);
            $this->verifyBatch(transfer: $transfer, token: $token, batch: $batch, cursor: $cursor, manifest: $manifest);

            return $batch;
        });
    }

    private function currentManifest(object $job, DataScope $scope, StepToken $token): SourceManifest
    {
        $manifest = $this->source->describe($scope);
        $pinned = Value::map(json_decode(Value::text(Value::field(row: $job, name: 'source_manifest')), true, flags: JSON_THROW_ON_ERROR));
        $current = Value::map(json_decode(json_encode($manifest, JSON_THROW_ON_ERROR), true, flags: JSON_THROW_ON_ERROR));

        if ($manifest->sha256 !== $token->sourceHash || $manifest->schemaHash !== $token->schemaHash
            || ($this->runtime !== null && ! Value::sameMap(left: array_intersect_key($pinned, $current), right: $current))) {
            throw new ImportUnavailable('Source changed; transfer resume is refused.');
        }

        return $manifest;
    }

    private function stepDigest(StepToken $token): string
    {
        return hash('sha256', json_encode(['transfer-step/1', $token, $this->limits], JSON_THROW_ON_ERROR));
    }

    private function storedReceipt(TransferRef $transfer, StepToken $token): ?TransferReceipt
    {
        if ($token->stepKey === null) {
            return null;
        }

        $row = $this->connection->table('corex_transfer_steps')->where('job_id', $transfer->id)->where('step_key', $token->stepKey)->first();

        if ($row === null) {
            return null;
        }

        if (! hash_equals(Value::text(Value::field(row: $row, name: 'digest')), $this->stepDigest($token))) {
            throw new ImportUnavailable('Transfer step digest has changed.');
        }
        $raw = Value::nullableText(Value::field(row: $row, name: 'receipt'));

        if ($raw === null) {
            return null;
        }

        $data = Value::map(json_decode($raw, true, flags: JSON_THROW_ON_ERROR));
        $cursor = Value::map($data['cursor'] ?? null);

        return new TransferReceipt(
            state: Value::text($data['state'] ?? null),
            cursor: new SourceCursor(ordinal: Value::text($cursor['ordinal'] ?? null), boundaryByteOffset: Value::nullableText($cursor['boundaryByteOffset'] ?? null), sourceHash: Value::text($cursor['sourceHash'] ?? null)),
            checkpointSequence: Value::text($data['checkpointSequence'] ?? null),
            stats: array_map(Value::integer(...), Value::map($data['stats'] ?? null)),
            gaps: [],
            errorCode: Value::nullableText($data['errorCode'] ?? null),
        );
    }

    private function saveReceipt(TransferRef $transfer, StepToken $token, TransferReceipt $receipt): void
    {
        if ($token->stepKey !== null) {
            $this->connection->table('corex_transfer_steps')->where('job_id', $transfer->id)->where('step_key', $token->stepKey)
                ->whereNull('receipt')->update(['receipt' => json_encode($receipt, JSON_THROW_ON_ERROR)]);
        }
    }

    private function verifyBatch(TransferRef $transfer, StepToken $token, RowBatch $batch, SourceCursor $cursor, SourceManifest $manifest): void
    {
        if ($token->stepKey === null) {
            return;
        }

        $ordinal = Value::integer($cursor->ordinal);
        foreach ($batch->rows as $row) {
            if (Value::integer($row->ordinal) <= $ordinal) {
                throw new ImportUnavailable('Transfer source batch is not advancing.');
            }
            $ordinal = Value::integer($row->ordinal);
        }

        if ($batch->next->sourceHash !== $manifest->sha256 || Value::integer($batch->next->ordinal) !== $ordinal
            || ($batch->remaining && $batch->rows === [])
            || ($batch->next->boundaryByteOffset !== null && Value::integer($batch->next->boundaryByteOffset) > $manifest->length)) {
            throw new ImportUnavailable('Transfer source checkpoint is inconsistent.');
        }

        $digest = hash('sha256', json_encode($batch, JSON_THROW_ON_ERROR));
        $query = $this->connection->table('corex_transfer_steps')->where('job_id', $transfer->id)->where('step_key', $token->stepKey);
        $previous = $query->value('batch_hash');

        if ($previous !== null && ! hash_equals(Value::text($previous), $digest)) {
            throw new ImportUnavailable('The admitted transfer batch has changed.');
        }

        if ($previous === null) {
            $query->update(['batch_hash' => $digest]);
        }
    }

    private function reconcileRow(DataScope $scope, TransferRef $transfer, CanonicalRecord $record, WriteToken $token): TargetResult
    {
        $key = $token->operationKey;
        $evidence = $this->references?->inspect(scope: $scope, namespace: 'transfer-sink', key: $key, ownerRef: $transfer->id, digest: $token->payloadHash);
        $result = $this->target->lookup(scope: $scope, token: $token);

        if ($result->status !== 'absent-with-proof') {
            if ($evidence !== null) {
                $this->observeRow(ticket: $evidence->ticket, result: $result, key: $key);
            }

            return $result;
        }

        if ($evidence !== null) {
            if ($evidence->observation?->kind === ProviderObservationKind::Confirmed) {
                throw new ImportUnavailable('The sink cannot prove the previously committed effect.');
            }
            $this->references->observe(ticket: $evidence->ticket, kind: ProviderObservationKind::Absent, reference: $key);
        }

        if ($this->cancellation->isCancelled()) {
            return $result;
        }
        $ticket = $this->references?->reserve(scope: $scope, namespace: 'transfer-sink', key: $key, ownerRef: $transfer->id, digest: $token->payloadHash, stamp: $this->mapping->definition, seconds: $this->limits->maxRuntimeSeconds, previous: $evidence);
        $result = $this->target->apply(scope: $scope, record: $record, rule: $this->conflictRule, token: $token);

        if ($ticket !== null) {
            $this->observeRow(ticket: $ticket, result: $result, key: $key);
        }

        return $result;
    }

    private function observeRow(AdmissionTicket $ticket, TargetResult $result, string $key): void
    {
        $kind = match ($result->status) {
            'applied', 'updated', 'noop' => ProviderObservationKind::Confirmed,
            'invalid', 'conflict', 'unsupported' => ProviderObservationKind::Permanent,
            default => ProviderObservationKind::Unknown,
        };
        $this->references?->observe(ticket: $ticket, kind: $kind, reference: $key);
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
        if (in_array(Value::field(row: $job, name: 'state'), ['completed', 'cancelled', 'failed'], true)) {
            throw new ImportUnavailable('Terminal transfers cannot execute another step.');
        }

        if (Value::text(Value::field(row: $job, name: 'checkpoint_seq')) !== $token->checkpointSequence || Value::text(Value::field(row: $job, name: 'claim_epoch')) !== $token->epoch || Value::field(row: $job, name: 'source_hash') !== $token->sourceHash || Value::field(row: $job, name: 'mapping_hash') !== $token->mappingHash || Value::field(row: $job, name: 'schema_hash') !== $token->schemaHash || Value::field(row: $job, name: 'restore_epoch') !== $token->restoreEpoch || ! hash_equals($this->mapping->sha256, $token->mappingHash) || ! hash_equals($this->mapping->schemaHash, $token->schemaHash)) {
            throw new ImportUnavailable('Transfer checkpoint token does not match the current job.');
        }

    }

    private function assertAuthority(object $job, DataScope $scope): void
    {
        SessionIdentity::assertScope(job: $job, scope: $scope);

        if (! $this->authorizer->authorize(scope: $scope, record: new RecordRef(entity: 'import-export.transfer', id: Value::text(Value::field(row: $job, name: 'id'))), fields: new FieldSelection([]), use: FieldUse::Write)->allowed) {
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

    private function recordResult(string $jobId, string $ordinal, TargetResult $result, string $payloadHash, string $epoch, string $key): void
    {
        $this->saveRow(jobId: $jobId, ordinal: $ordinal, result: $result->status, payloadHash: $payloadHash, epoch: $epoch, errorCode: $result->errorCode, providerTask: $result->providerTask, operationKey: $key);
    }

    private function writeToken(CanonicalRecord $record, TransferRef $transfer, StepToken $step, string $ordinal): WriteToken
    {
        return new WriteToken(
            operationKey: hash('sha256', json_encode([$step->stepKey === null ? null : [$transfer->id, $step->stepKey, $ordinal], $record->entity, $record->externalSystem, $record->externalId, $record->sourceRevision], JSON_THROW_ON_ERROR)),
            payloadHash: $record->payloadHash,
            expectedRecordVersion: 'absent',
        );
    }

    private function lockReference(DataScope $scope, string $jobId, CanonicalRecord $record): bool
    {
        $inserted = $this->connection->table('corex_transfer_refs')->insertOrIgnore([
            'id' => Value::text(Str::uuid()), 'job_id' => $jobId, 'logical_scope' => $this->logicalScope($scope), 'entity' => $record->entity,
            'external_system' => $record->externalSystem, 'external_id' => $record->externalId,
            'source_revision' => $record->sourceRevision, 'source_updated_at' => $record->sourceUpdatedAt,
            'payload_hash' => $record->payloadHash, 'state' => 'running', 'created_at' => $this->clock->now(), 'updated_at' => $this->clock->now(),
        ]);

        if ($inserted === 1) {
            return false;
        }

        $existing = $this->connection->table('corex_transfer_refs')
            ->where('logical_scope', $this->logicalScope($scope))
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

    private function storeReference(DataScope $scope, CanonicalRecord $record, TargetResult $result): void
    {
        $this->connection->table('corex_transfer_refs')
            ->where('logical_scope', $this->logicalScope($scope))
            ->where('entity', $record->entity)->where('external_system', $record->externalSystem)->where('external_id', $record->externalId)
            ->update(['target_id' => $result->target?->id, 'payload_hash' => $record->payloadHash, 'source_revision' => $record->sourceRevision, 'source_updated_at' => $record->sourceUpdatedAt, 'state' => $result->status, 'result' => json_encode(['status' => $result->status], JSON_THROW_ON_ERROR), 'updated_at' => $this->clock->now()]);
    }

    private function logicalScope(DataScope $scope): string
    {
        return hash('sha256', json_encode([$scope->accountId, $scope->accountIncarnation, $scope->workspaceId], JSON_THROW_ON_ERROR));
    }

    private function saveRow(string $jobId, string $ordinal, string $result, string $payloadHash, string $epoch, ?string $errorCode, ?string $providerTask = null, ?string $path = null, ?string $operationKey = null): void
    {
        $this->connection->table('corex_transfer_rows')->updateOrInsert(
            attributes: ['job_id' => $jobId, 'ordinal' => $ordinal],
            values: [
                'id' => Value::text(Str::uuid()),
                'external_identity_hash' => hash('sha256', $jobId.'|'.$ordinal),
                'operation_key' => $operationKey ?? hash('sha256', $jobId.'|'.$ordinal),
                'payload_hash' => $payloadHash,
                'result' => $result,
                'error' => $errorCode === null ? null : json_encode(['code' => $errorCode, 'path' => $path], JSON_THROW_ON_ERROR),
                'provider_task' => $providerTask,
                'epoch' => $epoch,
            ],
        );
    }
}
