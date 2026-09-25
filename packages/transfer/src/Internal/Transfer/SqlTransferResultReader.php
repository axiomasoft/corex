<?php

declare(strict_types=1);

namespace CoreX\Transfer\Internal\Transfer;

use CoreX\Contracts\DataScopeAuthorizer;
use CoreX\Support\Data\DataScope;
use CoreX\Transfer\Contracts\TransferResultReader;
use CoreX\Transfer\Data\TransferRef;
use Illuminate\Database\ConnectionInterface;
use Override;

final readonly class SqlTransferResultReader implements TransferResultReader
{
    public function __construct(
        private ConnectionInterface $connection,
        private DataScopeAuthorizer $authorizer,
    ) {}

    #[Override]
    public function failures(DataScope $scope, TransferRef $ref, ?string $afterOrdinal, int $limit): array
    {
        $this->authorize(scope: $scope, ref: $ref);

        if ($limit < 1 || $limit > 100) {
            throw new TransferUnavailable('Transfer failure page limit is outside 1..100.');
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
            $error = json_decode((string) $row->error, true);

            $records[] = [
                'ordinal' => (string) $row->ordinal,
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
        $cursor = json_decode((string) $job->cursor, true);
        $stats = json_decode((string) $job->stats, true);

        return [
            'id' => (string) $job->id,
            'state' => (string) $job->state,
            'mode' => (string) $job->mode,
            'checkpointSequence' => (string) $job->checkpoint_seq,
            'cursor' => is_array($cursor) ? [
                'ordinal' => isset($cursor['ordinal']) ? (string) $cursor['ordinal'] : '0',
                'boundaryByteOffset' => array_key_exists('boundaryByteOffset', $cursor) && $cursor['boundaryByteOffset'] !== null
                    ? (string) $cursor['boundaryByteOffset']
                    : null,
            ] : ['ordinal' => '0', 'boundaryByteOffset' => null],
            'stats' => is_array($stats) ? $stats : [],
        ];
    }

    private function authorize(DataScope $scope, TransferRef $ref): object
    {
        $job = $this->connection->table('corex_transfer_jobs')->where('id', $ref->id)->first();

        if ($job === null
            || ! hash_equals((string) $job->restore_epoch, $scope->restoreEpoch)
            || ! $this->authorizer->authorize(scope: $scope, entity: '*', subjectId: $ref->id, fields: [], action: 'transfer.read')->allowed) {
            throw new TransferUnavailable('Transfer result is not available under the current authority.');
        }

        return $job;
    }
}
