<?php

declare(strict_types=1);

namespace CoreX\Transfer\Internal\Export;

use CoreX\Contracts\DataScopeAuthorizer;
use CoreX\Contracts\TenantStorage;
use CoreX\Support\Data\DataScope;
use CoreX\Transfer\Contracts\ExportSource;
use CoreX\Transfer\Data\CanonicalRecord;
use CoreX\Transfer\Data\Export\ExportCursor;
use CoreX\Transfer\Data\Export\ExportManifest;
use CoreX\Transfer\Data\Export\ExportPart;
use CoreX\Transfer\Data\TransferLimits;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Str;
use JsonException;

final readonly class DatabaseExportRunner
{
    public function __construct(
        private ConnectionInterface $connection,
        private TenantStorage $storage,
        private ExportSource $source,
        private TransferLimits $limits,
        private CanonicalExportCodec $codec,
        private DataScopeAuthorizer $authorizer,
    ) {}

    public function seal(DataScope $scope, string $jobId, ExportManifest $draft, ExportCursor $after): ExportManifest
    {
        $this->assertCaptureAccess(scope: $scope, manifest: $draft);

        if ($draft->snapshotMode !== 'coherent') {
            throw new ExportUnavailable('Only a coherent manifest may be sealed.');
        }

        if ($this->abortUnsealedAttempt($draft->id)) {
            throw new ExportUnavailable('An interrupted unsealed capture requires a new manifest attempt.');
        }

        return $this->connection->transaction(function () use ($scope, $jobId, $draft, $after): ExportManifest {
            $this->assertCaptureAccess(scope: $scope, manifest: $draft);
            $this->connection->statement('SET LOCAL TRANSACTION ISOLATION LEVEL REPEATABLE READ');
            $existing = $this->connection->table('corex_export_manifests')->where('id', $draft->id)->lockForUpdate()->first();

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
                'roster' => json_encode($draft->roster, JSON_THROW_ON_ERROR),
                'parts_hash' => null,
                'snapshot_mode' => $draft->snapshotMode,
                'state' => 'capturing',
            ]);

            $parts = [];
            $cursor = $after;
            $partNumber = 0;

            do {
                $this->assertCaptureAccess(scope: $scope, manifest: $draft);
                $page = $this->source->page(scope: $scope, manifest: $draft, after: $cursor, limits: $this->limits);
                $part = $this->stagePart(manifest: $draft, number: (string) $partNumber, records: $page->rows);
                $parts[] = $part;
                $cursor = $page->next ?? $cursor;
                $partNumber++;

                if ($partNumber > $this->limits->batchRows) {
                    throw new ExportUnavailable('Export part count exceeds the qualified profile.');
                }
            } while (! $page->complete);

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
                roster: $draft->roster,
                partsHash: $partsHash,
            );
        }, attempts: 3);
    }

    public function emit(DataScope $scope, ExportManifest $manifest, ExportCursor $cursor): string
    {
        $this->assertEmitAccess(scope: $scope, manifest: $manifest, cursor: $cursor);
        $part = $this->connection->table('corex_export_parts')
            ->where('manifest_id', $manifest->id)
            ->where('part_no', $cursor->partNumber)
            ->first();

        if ($part === null || ! hash_equals($manifest->partsHash, $cursor->manifestHash)) {
            throw new ExportUnavailable('Export cursor does not address a sealed immutable part.');
        }

        $bytes = $this->storage->disk()->get($part->immutable_ref);

        if (! is_string($bytes) || strlen($bytes) !== (int) $part->byte_length || ! hash_equals($part->hash, hash('sha256', $bytes))) {
            throw new ExportUnavailable('Immutable export part verification failed.');
        }

        $this->assertEmitAccess(scope: $scope, manifest: $manifest, cursor: $cursor);

        return $bytes;
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

    private function assertCaptureAccess(DataScope $scope, ExportManifest $manifest): void
    {
        if (! $this->storage->capabilities()->export
            || ! hash_equals($scope->restoreEpoch, $manifest->restoreEpoch)
            || ! $this->authorizer->authorize(scope: $scope, entity: '*', subjectId: $manifest->id, fields: [], action: 'export.capture')->allowed) {
            throw new ExportUnavailable('Export capture is unavailable under the current authority.');
        }
    }

    private function abortUnsealedAttempt(string $manifestId): bool
    {
        return $this->connection->transaction(function () use ($manifestId): bool {
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
            || ! $this->authorizer->authorize(scope: $scope, entity: '*', subjectId: $manifest->id, fields: [], action: 'export.emit')->allowed) {
            throw new ExportUnavailable('Export output is denied by the current authority.');
        }
    }

    private function storedManifest(object $stored): ExportManifest
    {
        return new ExportManifest(
            id: $stored->id,
            snapshotMode: $stored->snapshot_mode,
            schemaHash: $stored->schema_hash,
            restoreEpoch: $stored->restore_epoch,
            roster: json_decode($stored->roster, true, flags: JSON_THROW_ON_ERROR),
            partsHash: $stored->parts_hash,
        );
    }
}
