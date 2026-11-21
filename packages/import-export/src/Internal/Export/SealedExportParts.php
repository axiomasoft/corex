<?php

declare(strict_types=1);

namespace CoreX\ImportExport\Internal\Export;

use CoreX\Data\Contracts\DataScopeAuthorizer;
use CoreX\Data\Data\DataScope;
use CoreX\Data\Data\FieldSelection;
use CoreX\Data\Data\RecordRef;
use CoreX\Data\Enums\FieldUse;
use CoreX\Data\Laravel\JsonDocuments;
use CoreX\ImportExport\Exceptions\ExportUnavailable;
use CoreX\ImportExport\Internal\SessionIdentity;
use CoreX\ImportExport\Internal\Value;
use CoreX\Storage\Contracts\TenantStorage;
use Illuminate\Database\ConnectionInterface;
use Packman\Attributes\Internal;
use Throwable;

#[Internal]
final readonly class SealedExportParts
{
    public function __construct(
        private ConnectionInterface $connection,
        private TenantStorage $storage,
        private DataScopeAuthorizer $authorizer,
        private ?CurrentExportSchema $schema,
    ) {}

    /** @param list<string> $fields */
    public function authorize(DataScope $scope, string $entity, string $id, FieldUse $use, array $fields = []): void
    {
        if (! $this->authorizer->authorize($scope, new RecordRef($entity, $id), new FieldSelection($fields), $use)->allowed) {
            throw new ExportUnavailable('Export is unavailable under current policy.');
        }
    }

    public function manifest(DataScope $scope, string $jobId, bool $published): object
    {
        if (! $this->storage->capabilities()->export) {
            throw new ExportUnavailable('Export storage is unavailable.');
        }
        $job = $this->connection->table('corex_transfer_jobs')->where('id', $jobId)->where('mode', 'export')->first()
            ?? throw new ExportUnavailable('Export job is unavailable.');
        SessionIdentity::assertScope($job, $scope);

        if ($published && Value::field($job, 'state') !== 'completed') {
            throw new ExportUnavailable('Export completion is unavailable.');
        }
        $this->authorize($scope, 'import-export.transfer', $jobId, FieldUse::Read);

        $manifest = $this->connection->table('corex_export_manifests')->where('job_id', $jobId)
            ->whereIn('state', $published ? ['published'] : ['sealed', 'published'])->first()
            ?? throw new ExportUnavailable('Export manifest is unavailable.');

        if (! hash_equals(Value::text(Value::field($manifest, 'restore_epoch')), $scope->restoreEpoch)
            || ! hash_equals(Value::text(Value::field($manifest, 'schema_hash')), Value::text(Value::field($job, 'schema_hash')))) {
            throw new ExportUnavailable('Export scope or schema has changed.');
        }
        $this->authorize($scope, 'import-export.manifest', Value::text(Value::field($manifest, 'id')), FieldUse::Export);
        $this->assertSchema($scope, $manifest);

        return $manifest;
    }

    /** @return array{manifestId: string, partsHash: string, parts: list<array{number: string, sha256: string, byteLength: int, records: int}>} */
    public function summary(DataScope $scope, object $manifest): array
    {
        $id = Value::text(Value::field($manifest, 'id'));
        $partsHash = Value::text(Value::field($manifest, 'parts_hash'));
        $parts = [];
        $identity = [];
        foreach ($this->connection->table('corex_export_parts')->where('manifest_id', $id)->orderBy('part_no')->get() as $part) {
            $number = Value::text(Value::field($part, 'part_no'));
            $hash = Value::text(Value::field($part, 'hash'));
            $length = Value::integer(Value::field($part, 'byte_length'));

            if ($number !== (string) count($parts)) {
                throw new ExportUnavailable('Export part roster is incomplete.');
            }
            $records = 0;
            $this->verifyBytes($scope, $part, $records);
            $parts[] = ['number' => $number, 'sha256' => $hash, 'byteLength' => $length, 'records' => $records];
            $identity[] = "{$number}:{$hash}:{$length}";
        }

        if ($parts === [] || ! hash_equals($partsHash, hash('sha256', implode("\n", $identity)))) {
            throw new ExportUnavailable('Export part roster digest failed.');
        }
        $this->authorize($scope, 'import-export.manifest', $id, FieldUse::Export);
        $this->assertSchema($scope, $manifest);

        return ['manifestId' => $id, 'partsHash' => $partsHash, 'parts' => $parts];
    }

    public function assertSchema(DataScope $scope, object $manifest): void
    {
        $this->schema?->assertCurrent(
            $scope,
            Value::text(Value::field($manifest, 'schema_hash')),
            JsonDocuments::capture(json_decode(Value::text(Value::field($manifest, 'roster')), true, flags: JSON_THROW_ON_ERROR)),
        );
    }

    public function verifyBytes(DataScope $scope, object $part, int &$count = 0): string
    {
        try {
            $bytes = $this->storage->disk()->get(Value::text(Value::field($part, 'immutable_ref')));
        } catch (Throwable $error) {
            throw new ExportUnavailable('Immutable export part could not be read.', previous: $error);
        }

        if (Value::field($part, 'state') !== 'verified' || ! is_string($bytes)
            || strlen($bytes) !== Value::integer(Value::field($part, 'byte_length'))
            || ! hash_equals(Value::text(Value::field($part, 'hash')), hash('sha256', $bytes))) {
            throw new ExportUnavailable('Immutable export part verification failed.');
        }

        $count = 0;
        foreach (explode("\n", rtrim($bytes, "\n")) as $line) {
            if ($line === '') {
                continue;
            }
            $record = Value::map(json_decode($line, true, flags: JSON_THROW_ON_ERROR));
            $fields = Value::map($record['values'] ?? null);
            $this->authorize($scope, Value::text($record['entity'] ?? null), Value::text($record['externalId'] ?? null), FieldUse::Export, array_keys($fields));
            $count++;
        }

        $counts = Value::map(json_decode(Value::text(Value::field($part, 'row_counts')), true, flags: JSON_THROW_ON_ERROR));

        if ($count !== ($counts['records'] ?? null)) {
            throw new ExportUnavailable('Export row count does not match its receipt.');
        }

        return $bytes;
    }
}
