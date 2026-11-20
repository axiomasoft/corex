<?php

declare(strict_types=1);

namespace CoreX\ImportExport\Internal\Export;

use CoreX\Data\Contracts\DataScopeAuthorizer;
use CoreX\Data\Data\DataScope;
use CoreX\Data\Data\FieldSelection;
use CoreX\Data\Data\RecordRef;
use CoreX\Data\Enums\FieldUse;
use CoreX\Data\Laravel\JsonDocuments;
use CoreX\ImportExport\Contracts\CancellationSignal;
use CoreX\ImportExport\Contracts\ExportRunner;
use CoreX\ImportExport\Contracts\ExportSource;
use CoreX\ImportExport\Data\CanonicalRecord;
use CoreX\ImportExport\Data\Export\ExportCursor;
use CoreX\ImportExport\Data\Export\ExportManifest;
use CoreX\ImportExport\Data\Export\ExportPart;
use CoreX\ImportExport\Data\SourceCursor;
use CoreX\ImportExport\Data\TransferLimits;
use CoreX\ImportExport\Exceptions\ExportUnavailable;
use CoreX\ImportExport\Internal\SessionIdentity;
use CoreX\ImportExport\Internal\SessionRuntime;
use CoreX\ImportExport\Internal\Transfer\ExecutionGuard;
use CoreX\ImportExport\Internal\Transfer\NeverCancelled;
use CoreX\ImportExport\Internal\Transfer\TransferLimitReached;
use CoreX\ImportExport\Internal\Value;
use CoreX\Storage\Contracts\TenantStorage;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Str;
use InvalidArgumentException;
use JsonException;
use Override;
use Packman\Attributes\Internal;

#[Internal]
final readonly class DatabaseExportRunner implements ExportRunner
{
    public function __construct(
        private ConnectionInterface $connection,
        private TenantStorage $storage,
        private ExportSource $source,
        private TransferLimits $limits,
        private CanonicalExportCodec $codec,
        private DataScopeAuthorizer $authorizer,
        private CancellationSignal $cancellation = new NeverCancelled,
        private ?ExecutionGuard $guard = null,
        private ?SessionRuntime $runtime = null,
    ) {}

    #[Override]
    public function seal(DataScope $scope, string $jobId, ExportManifest $draft, ExportCursor $after): ExportManifest
    {
        $this->runtime?->assertCurrent($this->connection, $scope);
        $this->runtime?->assertTransfer($jobId);
        $this->runtime?->assertExport(json_encode([$draft, $after], JSON_THROW_ON_ERROR));
        $this->assertCaptureAccess(scope: $scope, manifest: $draft);
        $this->assertJobScope($jobId, $scope);
        $existingJob = $this->connection->table('corex_export_manifests')->where('id', $draft->id)->value('job_id');

        if ($existingJob !== null && Value::text($existingJob) !== $jobId) {
            throw new ExportUnavailable('The export manifest belongs to another job.');
        }

        if ($draft->snapshotMode !== 'coherent') {
            throw new ExportUnavailable('Only a coherent manifest may be sealed.');
        }

        if ($this->abortUnsealedAttempt($scope, $draft->id)) {
            throw new ExportUnavailable('An interrupted unsealed capture requires a new manifest attempt.');
        }

        return $this->connection->transaction(function () use ($scope, $jobId, $draft, $after): ExportManifest {
            $this->connection->statement('SET LOCAL TRANSACTION ISOLATION LEVEL REPEATABLE READ');
            $this->runtime?->assertCurrent($this->connection, $scope);
            $this->assertCaptureAccess(scope: $scope, manifest: $draft);
            $existing = $this->connection->table('corex_export_manifests')->where('id', $draft->id)->lockForUpdate()->first();

            if ($existing !== null && Value::text($existing->job_id) !== $jobId) {
                throw new ExportUnavailable('The export manifest belongs to another job.');
            }

            if ($existing !== null && $existing->state === 'sealed') {
                return $this->storedManifest($existing);
            }

            if ($existing !== null) {
                throw new ExportUnavailable('Unsealed export attempts are not resumable.');
            }

            $this->connection->table('corex_export_manifests')->insert([
                'id' => $draft->id,
                'job_id' => $jobId,
                'capture_attempt' => hash('sha256', $draft->id.'|'.$draft->schemaHash.'|'.$draft->restoreEpoch),
                'schema_hash' => $draft->schemaHash,
                'restore_epoch' => $draft->restoreEpoch,
                'roster' => json_encode(JsonDocuments::map($draft->roster), JSON_THROW_ON_ERROR),
                'parts_hash' => null,
                'snapshot_mode' => $draft->snapshotMode,
                'state' => 'capturing',
            ]);

            $parts = [];
            $cursor = $after;
            $partNumber = 0;
            $guard = $this->guard ?? ExecutionGuard::production(limits: $this->limits);
            $written = [];

            try {
                do {
                    $this->assertCaptureAccess(scope: $scope, manifest: $draft);
                    $this->assertBudget(guard: $guard, manifest: $draft);

                    if ($this->cancellation->isCancelled()) {
                        throw new ExportUnavailable('Export capture was cancelled.');
                    }

                    $page = $this->source->page(scope: $scope, manifest: $draft, after: $cursor, limits: $this->limits);
                    $this->assertPageBounds(records: $page->rows);
                    foreach ($page->rows as $record) {
                        $this->authorizeRecord($scope, $record->entity, $record->externalId, array_keys($record->values));
                    }
                    $this->assertBudget(guard: $guard, manifest: $draft);

                    if ($this->cancellation->isCancelled()) {
                        throw new ExportUnavailable('Export capture was cancelled.');
                    }

                    $part = $this->stagePart(manifest: $draft, number: (string) $partNumber, records: $page->rows);
                    $written[] = $part->immutableRef;
                    $parts[] = $part;
                    $cursor = $page->next ?? $cursor;
                    $partNumber++;
                } while (! $page->complete);
            } catch (TransferLimitReached|ExportUnavailable $failed) {
                $this->discardOrphans(refs: $written);

                throw $failed;
            }

            $partsHash = hash('sha256', implode("\n", array_map(
                static fn (ExportPart $part): string => "{$part->number}:{$part->sha256}:{$part->byteLength}",
                $parts,
            )));

            if ($draft->partsHash !== str_repeat('0', 64) && ! hash_equals($draft->partsHash, $partsHash)) {
                $this->connection->table('corex_export_manifests')->where('id', $draft->id)->update(['state' => 'aborted']);

                throw new ExportUnavailable('Export part verification does not match the supplied manifest.');
            }

            $this->connection->table('corex_export_manifests')->where('id', $draft->id)->update([
                'parts_hash' => $partsHash,
                'state' => 'sealed',
            ]);
            $this->connection->table('corex_transfer_jobs')->where('id', $jobId)->update(['state' => 'completed']);

            return new ExportManifest(
                id: $draft->id,
                snapshotMode: 'coherent',
                schemaHash: $draft->schemaHash,
                restoreEpoch: $draft->restoreEpoch,
                roster: JsonDocuments::capture($draft->roster),
                partsHash: $partsHash,
            );
        }, attempts: 3);
    }

    #[Override]
    public function emit(DataScope $scope, ExportManifest $manifest, ExportCursor $cursor): string
    {
        $this->runtime?->assertCurrent($this->connection, $scope);
        $this->assertEmitAccess(scope: $scope, manifest: $manifest, cursor: $cursor);
        $stored = $this->connection->table('corex_export_manifests')->where('id', $manifest->id)->first();
        $part = $this->connection->table('corex_export_parts')
            ->where('manifest_id', $manifest->id)
            ->where('part_no', $cursor->partNumber)
            ->first();

        if ($stored === null || $stored->state !== 'sealed' || ! hash_equals(Value::text($stored->parts_hash), $manifest->partsHash)) {
            throw new ExportUnavailable('Export cursor does not address a sealed immutable part.');
        }

        $this->assertJobScope(Value::text($stored->job_id), $scope);

        if ($part === null || ! hash_equals($manifest->partsHash, $cursor->manifestHash)) {
            throw new ExportUnavailable('Export cursor does not address a sealed immutable part.');
        }

        $bytes = $this->storage->disk()->get(Value::text($part->immutable_ref));

        if (! is_string($bytes) || strlen($bytes) !== Value::integer($part->byte_length) || ! hash_equals(Value::text($part->hash), hash('sha256', $bytes))) {
            throw new ExportUnavailable('Immutable export part verification failed.');
        }

        foreach (explode("\n", rtrim($bytes, "\n")) as $line) {
            if ($line === '') {
                continue;
            }

            try {
                $record = Value::map(json_decode($line, true, flags: JSON_THROW_ON_ERROR));
                $fields = Value::map($record['values'] ?? null);
                $this->authorizeRecord($scope, Value::text($record['entity'] ?? null), Value::text($record['externalId'] ?? null), array_keys($fields));
            } catch (InvalidArgumentException|JsonException $exception) {
                throw new ExportUnavailable('The sealed export projection cannot be verified.', previous: $exception);
            }
        }
        $this->assertEmitAccess(scope: $scope, manifest: $manifest, cursor: $cursor);

        return $bytes;
    }

    /** @param list<string> $fields */
    private function authorizeRecord(DataScope $scope, string $entity, string $id, array $fields): void
    {
        try {
            $decision = $this->authorizer->authorize($scope, new RecordRef($entity, $id), new FieldSelection($fields), FieldUse::Export);
        } catch (InvalidArgumentException $exception) {
            throw new ExportUnavailable('The export record cannot be authorized.', previous: $exception);
        }

        if (! $decision->allowed) {
            throw new ExportUnavailable('An export record is unavailable under the current field policy.');
        }
    }

    /** @param list<CanonicalRecord> $records */
    private function stagePart(ExportManifest $manifest, string $number, array $records): ExportPart
    {
        try {
            $bytes = $this->codec->rawNdjson(records: $records, maxBytes: $this->limits->maxFileBytes);
        } catch (JsonException $exception) {
            throw new ExportUnavailable('Canonical export payload cannot be encoded.', previous: $exception);
        }

        $hash = hash('sha256', $bytes);
        $ref = "corex-exports/{$manifest->id}/{$number}-{$hash}.ndjson";
        $disk = $this->storage->disk();

        if (! $disk->exists($ref)) {
            if ($disk->put($ref, $bytes) !== true) {
                throw new ExportUnavailable('Immutable export part could not be persisted.');
            }
        }

        $stored = $disk->get($ref);

        if (! is_string($stored) || ! hash_equals($hash, hash('sha256', $stored)) || strlen($stored) !== strlen($bytes)) {
            throw new ExportUnavailable('Immutable export part storage acknowledgement is unknown.');
        }

        $part = new ExportPart(number: $number, immutableRef: $ref, sha256: $hash, byteLength: strlen($bytes), rowCounts: ['records' => count($records)]);
        $this->connection->table('corex_export_parts')->updateOrInsert(
            attributes: ['manifest_id' => $manifest->id, 'part_no' => $number],
            values: [
                'id' => (string) Str::uuid(),
                'immutable_ref' => $part->immutableRef,
                'hash' => $part->sha256,
                'byte_length' => $part->byteLength,
                'row_counts' => json_encode($part->rowCounts, JSON_THROW_ON_ERROR),
                'state' => 'verified',
            ],
        );

        return $part;
    }

    private function assertBudget(ExecutionGuard $guard, ExportManifest $manifest): void
    {
        $guard->assertWithin(
            cursor: new SourceCursor(ordinal: '0', boundaryByteOffset: '0', sourceHash: $manifest->schemaHash),
            checkpointSequence: '0',
            stats: [],
        );
    }

    private function assertJobScope(string $jobId, DataScope $scope): void
    {
        $this->runtime?->assertTransfer($jobId);
        $job = $this->connection->table('corex_transfer_jobs')->where('id', $jobId)->first();

        if ($job === null) {
            throw new ExportUnavailable('The export job is unavailable.');
        }

        SessionIdentity::assertScope($job, $scope);
    }

    /** @param list<CanonicalRecord> $records */
    private function assertPageBounds(array $records): void
    {
        if (count($records) > $this->limits->batchRows) {
            throw new ExportUnavailable('Export page exceeds the qualified row limit.');
        }
    }

    /** @param list<string> $refs */
    private function discardOrphans(array $refs): void
    {
        $disk = $this->storage->disk();

        foreach ($refs as $ref) {
            if ($disk->exists($ref)) {
                $disk->delete($ref);
            }
        }
    }

    private function assertCaptureAccess(DataScope $scope, ExportManifest $manifest): void
    {
        if (! $this->storage->capabilities()->export
            || ! hash_equals($scope->restoreEpoch, $manifest->restoreEpoch)
            || ! $this->authorizer->authorize($scope, new RecordRef('import-export.manifest', $manifest->id), new FieldSelection([]), FieldUse::Export)->allowed) {
            throw new ExportUnavailable('Export capture is unavailable under the current authority.');
        }
    }

    private function abortUnsealedAttempt(DataScope $scope, string $manifestId): bool
    {
        return $this->connection->transaction(function () use ($scope, $manifestId): bool {
            $this->runtime?->assertCurrent($this->connection, $scope);
            $manifest = $this->connection->table('corex_export_manifests')->where('id', $manifestId)->lockForUpdate()->first();

            if ($manifest === null || $manifest->state !== 'capturing') {
                return false;
            }

            $this->connection->table('corex_export_manifests')->where('id', $manifestId)->update(['state' => 'aborted']);

            return true;
        }, attempts: 3);
    }

    private function assertEmitAccess(DataScope $scope, ExportManifest $manifest, ExportCursor $cursor): void
    {
        if (! hash_equals($scope->restoreEpoch, $manifest->restoreEpoch)
            || ! hash_equals($cursor->manifestHash, $manifest->partsHash)
            || ! $this->authorizer->authorize($scope, new RecordRef('import-export.manifest', $manifest->id), new FieldSelection([]), FieldUse::Export)->allowed) {
            throw new ExportUnavailable('Export output is denied by the current authority.');
        }
    }

    private function storedManifest(object $stored): ExportManifest
    {
        return new ExportManifest(
            id: Value::text(Value::field(row: $stored, name: 'id')),
            snapshotMode: Value::text(Value::field(row: $stored, name: 'snapshot_mode')),
            schemaHash: Value::text(Value::field(row: $stored, name: 'schema_hash')),
            restoreEpoch: Value::text(Value::field(row: $stored, name: 'restore_epoch')),
            roster: JsonDocuments::capture(json_decode(Value::text(Value::field(row: $stored, name: 'roster')), true, flags: JSON_THROW_ON_ERROR)),
            partsHash: Value::text(Value::field(row: $stored, name: 'parts_hash')),
        );
    }
}
