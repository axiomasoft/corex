<?php

declare(strict_types=1);

namespace CoreX\ImportExport\Internal\Transfer;

use CoreX\Data\Contracts\DataScopeAuthorizer;
use CoreX\Data\Data\DataScope;
use CoreX\ImportExport\Contracts\TransferResultReader;
use CoreX\ImportExport\Data\TransferRef;
use CoreX\ImportExport\Exceptions\ImportUnavailable;
use CoreX\ImportExport\Internal\SessionIdentity;
use CoreX\ImportExport\Internal\SessionRuntime;
use CoreX\ImportExport\Internal\Value;
use Illuminate\Database\ConnectionInterface;
use Override;

final readonly class SqlTransferResultReader implements TransferResultReader
{
    public function __construct(
        private ConnectionInterface $connection,
        private DataScopeAuthorizer $authorizer,
        private ?SessionRuntime $runtime = null,
    ) {}

    #[Override]
    public function failures(DataScope $scope, TransferRef $ref, ?string $afterOrdinal, int $limit): array
    {
        $this->authorize(scope: $scope, ref: $ref);

        if ($limit < 1 || $limit > 100) {
            throw new ImportUnavailable('Transfer failure page limit is outside 1..100.');
        }

        $query = $this->connection->table('corex_transfer_rows')
            ->where('job_id', $ref->id)
            ->whereNotNull('error')
            ->orderBy('ordinal');

        if ($afterOrdinal !== null) {
            $query->where('ordinal', '>', $afterOrdinal);
        }

        $rows = $query->limit($limit + 1)->get();
        $page = $rows->take($limit);
        $records = [];

        foreach ($page as $row) {
            $error = json_decode(Value::text($row->error), true);

            $records[] = [
                'ordinal' => Value::text($row->ordinal),
                'code' => is_array($error) && is_string($error['code'] ?? null) ? $error['code'] : 'rowFailed',
                'path' => is_array($error) && is_string($error['path'] ?? null) ? $error['path'] : null,
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

    private function authorize(DataScope $scope, TransferRef $ref): object
    {
        $this->runtime?->assertCurrent($this->connection, $scope);
        $this->runtime?->assertTransfer($ref->id);
        $job = $this->connection->table('corex_transfer_jobs')->where('id', $ref->id)->first();

        if ($job === null
            || ! hash_equals(Value::text($job->restore_epoch), $scope->restoreEpoch)
            || ! $this->authorizer->authorize(scope: $scope, entity: '*', subjectId: $ref->id, fields: [], action: 'transfer.read')->allowed) {
            throw new ImportUnavailable('Transfer result is not available under the current authority.');
        }

        SessionIdentity::assertScope($job, $scope);

        return $job;
    }
}
