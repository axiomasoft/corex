<?php

declare(strict_types=1);

namespace CoreX\ImportExport\Internal\Transfer;

use CoreX\Data\Contracts\DataScopeAuthorizer;
use CoreX\Data\Data\DataScope;
use CoreX\Data\Data\FieldSelection;
use CoreX\Data\Data\RecordRef;
use CoreX\Data\Enums\FieldUse;
use CoreX\ImportExport\Contracts\TransferResultReader;
use CoreX\ImportExport\Data\TransferRef;
use CoreX\ImportExport\Data\Validation;
use CoreX\ImportExport\Exceptions\ExportUnavailable;
use CoreX\ImportExport\Exceptions\ImportUnavailable;
use CoreX\ImportExport\Internal\Export\CurrentExportSchema;
use CoreX\ImportExport\Internal\Export\SealedExportParts;
use CoreX\ImportExport\Internal\SessionIdentity;
use CoreX\ImportExport\Internal\SessionRuntime;
use CoreX\ImportExport\Internal\Value;
use CoreX\Storage\Contracts\TenantStorage;
use Illuminate\Database\Connection;
use Override;
use Packman\Attributes\Internal;

#[Internal]
final readonly class SqlTransferResultReader implements TransferResultReader
{
    public function __construct(
        private Connection $connection,
        private DataScopeAuthorizer $authorizer,
        private ?SessionRuntime $runtime = null,
        private ?TenantStorage $storage = null,
        private ?CurrentExportSchema $schema = null,
    ) {}

    #[Override]
    public function failures(DataScope $scope, TransferRef $ref, ?string $afterOrdinal, int $limit): array
    {
        $job = $this->authorize(scope: $scope, ref: $ref);

        if ($limit < 1 || $limit > 100) {
            throw new ImportUnavailable('Transfer failure page limit is outside 1..100.');
        }

        if ($afterOrdinal !== null) {
            Validation::unsignedInteger(value: $afterOrdinal, name: 'transfer failure cursor');
        }

        if (Value::field(row: $job, name: 'mode') === 'export') {
            $this->publishedExport(scope: $scope, ref: $ref);

            return ['records' => [], 'next' => null];
        }

        $query = $this->connection->table('corex_transfer_rows')
            ->where('job_id', $ref->id)
            ->whereIn('result', ['invalid', 'conflict', 'unsupported'])
            ->orderBy('ordinal');

        if ($afterOrdinal !== null) {
            $query->where('ordinal', '>', $afterOrdinal);
        }

        $rows = $query->limit($limit + 1)->get();
        $page = $rows->take($limit);
        $records = [];

        foreach ($page as $row) {
            $error = $row->error === null ? [] : json_decode(Value::text($row->error), true);
            $code = is_array($error) ? $error['code'] ?? null : null;
            $path = is_array($error) ? $error['path'] ?? null : null;

            $records[] = [
                'ordinal' => Value::text($row->ordinal),
                'code' => is_string($code) && preg_match('/\A[A-Za-z][A-Za-z0-9_.:-]{0,127}\z/D', $code) === 1 ? $code : 'rowFailed',
                'path' => is_string($path) && strlen($path) <= 512 && preg_match('/\A(?:\/(?:[^~\x00-\x1f\x7f]|~[01])*)*\z/Du', $path) === 1 ? $path : null,
            ];
        }

        $last = $records === [] ? null : $records[array_key_last($records)]['ordinal'];

        return [
            'records' => $records,
            'next' => $rows->count() > $limit ? $last : null,
        ];
    }

    #[Override]
    public function result(DataScope $scope, TransferRef $ref): array
    {
        $job = $this->authorize(scope: $scope, ref: $ref);

        if (Value::field(row: $job, name: 'mode') === 'export') {
            return $this->publishedExport(scope: $scope, ref: $ref);
        }
        $cursor = json_decode(Value::text(Value::field(row: $job, name: 'cursor')), true);
        $stats = json_decode(Value::text(Value::field(row: $job, name: 'stats')), true);

        return [
            'id' => Value::text(Value::field(row: $job, name: 'id')),
            'state' => Value::text(Value::field(row: $job, name: 'state')),
            'mode' => Value::text(Value::field(row: $job, name: 'mode')),
            'checkpointSequence' => Value::text(Value::field(row: $job, name: 'checkpoint_seq')),
            'cursor' => is_array($cursor) ? [
                'ordinal' => isset($cursor['ordinal']) ? Value::text($cursor['ordinal']) : '0',
                'boundaryByteOffset' => array_key_exists('boundaryByteOffset', $cursor) && $cursor['boundaryByteOffset'] !== null
                    ? Value::text($cursor['boundaryByteOffset'])
                    : null,
            ] : ['ordinal' => '0', 'boundaryByteOffset' => null],
            'stats' => is_array($stats) ? $stats : [],
        ];
    }

    /** @return array{manifestId: string, partsHash: string, parts: list<array{number: string, sha256: string, byteLength: int, records: int}>} */
    private function publishedExport(DataScope $scope, TransferRef $ref): array
    {
        if ($this->storage === null || $this->connection->transactionLevel() !== 0) {
            throw new ExportUnavailable('Export read requires an independent owner snapshot.');
        }

        return $this->connection->transaction(function () use ($scope, $ref): array {
            if ($this->connection->getDriverName() === 'pgsql') {
                $this->connection->statement('SET LOCAL TRANSACTION ISOLATION LEVEL REPEATABLE READ READ ONLY');
            }
            $this->runtime?->assertCurrent(connection: $this->connection, scope: $scope);
            $verify = new SealedExportParts(connection: $this->connection, storage: $this->storage, authorizer: $this->authorizer, schema: $this->schema);

            return $verify->summary(scope: $scope, manifest: $verify->manifest(scope: $scope, jobId: $ref->id, published: true));
        });
    }

    private function authorize(DataScope $scope, TransferRef $ref): object
    {
        $this->runtime?->assertCurrent(connection: $this->connection, scope: $scope);
        $this->runtime?->assertTransfer($ref->id);
        $job = $this->connection->table('corex_transfer_jobs')->where('id', $ref->id)->first();

        if ($job === null || ! hash_equals(Value::text($job->restore_epoch), $scope->restoreEpoch)) {
            throw new ImportUnavailable('Transfer result is not available under the current authority.');
        }

        SessionIdentity::assertScope(job: $job, scope: $scope);

        if (! $this->authorizer->authorize(scope: $scope, record: new RecordRef(entity: 'import-export.transfer', id: $ref->id), fields: new FieldSelection([]), use: FieldUse::Read)->allowed) {
            throw new ImportUnavailable('Transfer result is not available under the current authority.');
        }

        return $job;
    }
}
