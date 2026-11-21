<?php

declare(strict_types=1);

namespace CoreX\ImportExport\Internal\Export;

use CoreX\Context\Enums\ProviderObservationKind;
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
use CoreX\ImportExport\Internal\TransferReferences;
use CoreX\ImportExport\Internal\Value;
use CoreX\Storage\Contracts\TenantStorage;
use Illuminate\Database\Connection;
use Illuminate\Support\Str;
use InvalidArgumentException;
use JsonException;
use Override;
use Packman\Attributes\Internal;

#[Internal]
final readonly class DatabaseExportRunner implements ExportRunner
{
    public function __construct(
        private Connection $connection,
        private TenantStorage $storage,
        private ExportSource $source,
        private TransferLimits $limits,
        private CanonicalExportCodec $codec,
        private DataScopeAuthorizer $authorizer,
        private CancellationSignal $cancellation = new NeverCancelled,
        private ?ExecutionGuard $guard = null,
        private ?SessionRuntime $runtime = null,
        private ?TransferReferences $references = null,
        private bool $limitCaptureRows = false,
        private ?CurrentExportSchema $schema = null,
    ) {}

    #[Override]
    public function seal(DataScope $scope, string $jobId, ExportManifest $draft, ExportCursor $after): ExportManifest
    {
        $this->runtime?->assertCurrent($this->connection, $scope);
        $this->runtime?->assertTransfer($jobId);
        $this->runtime?->assertExport(json_encode([$draft, $after], JSON_THROW_ON_ERROR));
        $this->assertCaptureAccess(scope: $scope, manifest: $draft);
        $this->assertJobScope($jobId, $scope);

        if ($draft->snapshotMode !== 'coherent' || $this->connection->transactionLevel() !== 0) {
            throw new ExportUnavailable('Coherent capture requires an independent owner transaction.');
        }

        $this->prepareCapture($scope, $jobId, $draft);
        $guard = $this->guard ?? ExecutionGuard::production($this->limits);

        return $this->connection->transaction(function () use ($scope, $jobId, $draft, $after, $guard): ExportManifest {
            if ($this->connection->getDriverName() === 'pgsql') {
                $this->connection->statement('SET LOCAL TRANSACTION ISOLATION LEVEL REPEATABLE READ');
            }
            $this->runtime?->assertCurrent($this->connection, $scope);
            $this->assertCaptureAccess(scope: $scope, manifest: $draft);
            $existing = $this->connection->table('corex_export_manifests')->where('id', $draft->id)->where('job_id', $jobId)
                ->lockForUpdate()->first() ?? throw new ExportUnavailable('The export capture is unavailable.');

            if (in_array(Value::field($existing, 'state'), ['sealed', 'published'], true)) {
                return $this->storedManifest($existing);
            }

            $this->references?->enlist($scope, $this->connection, JsonDocuments::map($draft->roster));
            $parts = [];
            $cursor = $after;
            $partNumber = 0;
            $rows = 0;
            do {
                $this->assertCaptureAccess(scope: $scope, manifest: $draft);
                $this->assertBudget(guard: $guard, manifest: $draft);

                if ($this->cancellation->isCancelled()) {
                    throw new ExportUnavailable('Export capture was cancelled.');
                }

                $page = $this->source->page(scope: $scope, manifest: $draft, after: $cursor, limits: $this->limits);
                $this->assertPageBounds(records: $page->rows);
                $rows += count($page->rows);

                if (($this->limitCaptureRows && $rows > $this->limits->batchRows)
                    || (! $page->complete && ($page->next === null || $page->next->manifestHash !== $after->manifestHash
                        || ((int) $page->next->partNumber < (int) $cursor->partNumber)
                        || ($page->next->partNumber === $cursor->partNumber && (int) $page->next->rowOrdinal <= (int) $cursor->rowOrdinal)
                        || $page->rows === []))) {
                    throw new ExportUnavailable('Export capture is outside its bounded snapshot.');
                }
                foreach ($page->rows as $record) {
                    $this->authorizeRecord($scope, $record->entity, $record->externalId, array_keys($record->values));
                }
                $this->assertBudget(guard: $guard, manifest: $draft);

                if ($this->cancellation->isCancelled()) {
                    throw new ExportUnavailable('Export capture was cancelled.');
                }

                $parts[] = $this->stagePart($scope, $jobId, $draft, (string) $partNumber, $page->rows);
                $cursor = $page->next ?? $cursor;
                $partNumber++;
            } while (! $page->complete);
            $this->assertBudget(guard: $guard, manifest: $draft);
            $this->assertCaptureAccess($scope, $draft);

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
        if ($this->connection->transactionLevel() !== 0) {
            throw new ExportUnavailable('Export emit requires an independent owner snapshot.');
        }

        return $this->connection->transaction(function () use ($scope, $manifest, $cursor): string {
            if ($this->connection->getDriverName() === 'pgsql') {
                $this->connection->statement('SET LOCAL TRANSACTION ISOLATION LEVEL REPEATABLE READ READ ONLY');
            }
            $this->runtime?->assertCurrent($this->connection, $scope);
            $this->assertEmitAccess($scope, $manifest, $cursor);
            $jobId = $this->connection->table('corex_export_manifests')->where('id', $manifest->id)->value('job_id');

            if (! is_string($jobId)) {
                throw new ExportUnavailable('Export cursor does not address a sealed immutable part.');
            }

            $this->assertJobScope($jobId, $scope);
            $verify = new SealedExportParts($this->connection, $this->storage, $this->authorizer, $this->schema);
            $stored = $verify->manifest($scope, $jobId, published: false);
            $part = $this->connection->table('corex_export_parts')->where('manifest_id', $manifest->id)
                ->where('part_no', $cursor->partNumber)->first();

            if ($part === null || $cursor->rowOrdinal !== '0' || Value::field($stored, 'id') !== $manifest->id
                || Value::field($stored, 'parts_hash') !== $manifest->partsHash || Value::field($stored, 'schema_hash') !== $manifest->schemaHash) {
                throw new ExportUnavailable('Export cursor does not address a sealed immutable part.');
            }

            $bytes = $verify->verifyBytes($scope, $part);
            $this->assertEmitAccess($scope, $manifest, $cursor);
            $verify->assertSchema($scope, $stored);

            return $bytes;
        });
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
    private function stagePart(DataScope $scope, string $jobId, ExportManifest $manifest, string $number, array $records): ExportPart
    {
        try {
            $bytes = $this->codec->rawNdjson(records: $records, maxBytes: $this->limits->maxFileBytes);
        } catch (JsonException $exception) {
            throw new ExportUnavailable('Canonical export payload cannot be encoded.', previous: $exception);
        }

        $hash = hash('sha256', $bytes);
        $ref = "corex-exports/{$manifest->id}/{$number}-{$hash}.ndjson";
        $disk = $this->storage->disk();

        $effectKey = hash('sha256', json_encode(['export-part/1', $manifest->id, $number], JSON_THROW_ON_ERROR));
        $previous = $this->references?->inspect($scope, 'export-part', $effectKey, $jobId, $hash);
        $ticket = $previous?->ticket;

        if (! $disk->exists($ref)) {
            if ($previous?->observation?->kind === ProviderObservationKind::Confirmed) {
                throw new ExportUnavailable('Previously verified export part is missing.');
            }

            if ($ticket !== null) {
                $this->references->observe($ticket, ProviderObservationKind::Absent, $effectKey);
            }
            $ticket = $this->references?->reserve($scope, 'export-part', $effectKey, $jobId, $hash, JsonDocuments::map($manifest->roster), $this->limits->maxRuntimeSeconds, $previous);

            if ($disk->put($ref, $bytes) !== true) {
                throw new ExportUnavailable('Immutable export part could not be persisted.');
            }
        }

        $stored = $disk->get($ref);

        if (! is_string($stored) || ! hash_equals($hash, hash('sha256', $stored)) || strlen($stored) !== strlen($bytes)) {
            throw new ExportUnavailable('Immutable export part storage acknowledgement is unknown.');
        }

        if ($ticket !== null) {
            $this->references?->observe($ticket, ProviderObservationKind::Confirmed, $effectKey);
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

    private function assertCaptureAccess(DataScope $scope, ExportManifest $manifest): void
    {
        if (! $this->storage->capabilities()->export
            || ! hash_equals($scope->restoreEpoch, $manifest->restoreEpoch)
            || ! $this->authorizer->authorize($scope, new RecordRef('import-export.manifest', $manifest->id), new FieldSelection([]), FieldUse::Export)->allowed) {
            throw new ExportUnavailable('Export capture is unavailable under the current authority.');
        }
        $this->schema?->assertCurrent($scope, $manifest->schemaHash, $manifest->roster);
    }

    private function prepareCapture(DataScope $scope, string $jobId, ExportManifest $draft): void
    {
        $this->connection->transaction(function () use ($scope, $jobId, $draft): void {
            $this->runtime?->assertCurrent($this->connection, $scope);
            $this->assertJobScope($jobId, $scope);
            $job = $this->connection->table('corex_transfer_jobs')->where('id', $jobId)->lockForUpdate()->first();

            if ($job === null || Value::field($job, 'mode') !== 'export' || Value::field($job, 'schema_hash') !== $draft->schemaHash) {
                throw new ExportUnavailable('Export capture does not match its job.');
            }
            $existing = $this->connection->table('corex_export_manifests')->where('job_id', $jobId)->orWhere('id', $draft->id)->first();
            $attempt = hash('sha256', json_encode([$jobId, $draft], JSON_THROW_ON_ERROR));

            if ($existing !== null) {
                if (Value::field($existing, 'job_id') !== $jobId || Value::field($existing, 'id') !== $draft->id || Value::field($existing, 'capture_attempt') !== $attempt
                    || ! in_array(Value::field($existing, 'state'), ['capturing', 'sealed', 'published'], true)) {
                    throw new ExportUnavailable('Export capture identity has changed.');
                }

                return;
            }
            $this->references?->enlist($scope, $this->connection, JsonDocuments::map($draft->roster));
            $this->connection->table('corex_export_manifests')->insert([
                'id' => $draft->id, 'job_id' => $jobId, 'capture_attempt' => $attempt, 'schema_hash' => $draft->schemaHash,
                'restore_epoch' => $draft->restoreEpoch, 'roster' => json_encode(JsonDocuments::map($draft->roster), JSON_THROW_ON_ERROR),
                'parts_hash' => null, 'snapshot_mode' => $draft->snapshotMode, 'state' => 'capturing',
            ]);
        });
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
