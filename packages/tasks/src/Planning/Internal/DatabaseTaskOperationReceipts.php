<?php

declare(strict_types=1);

namespace CoreX\Tasks\Planning\Internal;

use CoreX\Data\Laravel\JsonDocuments;
use CoreX\Tasks\Planning\Contracts\TaskOperationReceipts;
use CoreX\Tasks\Planning\Data\TaskOperationKind;
use CoreX\Tasks\Planning\Data\TaskOperationReceipt;
use Illuminate\Database\Connection;
use Illuminate\Support\Str;
use Override;
use Psr\Clock\ClockInterface;
use UnexpectedValueException;

/** @internal SQL representation of the tasks-owned operation ledger. */
final class DatabaseTaskOperationReceipts implements TaskOperationReceipts
{
    public function __construct(private readonly ClockInterface $clock) {}

    #[Override]
    public function find(Connection $connection, string $requestKey): ?TaskOperationReceipt
    {
        $row = $connection->table('corex_task_operations')->where('request_key', $requestKey)->first();

        if ($row === null) {
            return null;
        }

        $result = json_decode((string) $row->result_ref, true, flags: JSON_THROW_ON_ERROR);

        if (! is_array($result)) {
            throw new UnexpectedValueException('Task operation result_ref must decode to an array.');
        }

        return new TaskOperationReceipt(
            requestKey: (string) $row->request_key,
            payloadHash: (string) $row->payload_hash,
            resultRef: JsonDocuments::capture($result),
        );
    }

    /** @param array<string, mixed> $resultRef */
    #[Override]
    public function record(Connection $connection, TaskOperationKind $kind, string $requestKey, string $payloadHash, string $restoreEpoch, array $resultRef): void
    {
        $connection->table('corex_task_operations')->insert([
            'id' => (string) Str::uuid7(),
            'request_key' => $requestKey,
            'payload_hash' => $payloadHash,
            'kind' => $kind->value,
            'result_ref' => json_encode($resultRef, JSON_THROW_ON_ERROR),
            'restore_epoch' => $restoreEpoch,
            'created_at' => $this->clock->now(),
        ]);
    }
}
