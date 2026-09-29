<?php

declare(strict_types=1);

namespace CoreX\Tasks\Planning\Contracts;

use CoreX\Tasks\Planning\Data\TaskOperationKind;
use CoreX\Tasks\Planning\Data\TaskOperationReceipt;
use Illuminate\Database\Connection;

/** Tasks-owned ledger port. The supplied connection preserves the caller's transaction. */
interface TaskOperationReceipts
{
    public function find(Connection $connection, string $requestKey): ?TaskOperationReceipt;

    /**
     * A duplicate key raises QueryException; callers must read and compare the
     * stored hash before treating it as an idempotent replay.
     *
     * @param  array<string, mixed>  $resultRef
     */
    public function record(Connection $connection, TaskOperationKind $kind, string $requestKey, string $payloadHash, string $restoreEpoch, array $resultRef): void;
}
