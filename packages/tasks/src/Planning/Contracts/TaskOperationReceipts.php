<?php

declare(strict_types=1);

namespace CoreX\Tasks\Planning\Contracts;

use CoreX\Tasks\Planning\Data\TaskOperationIdentity;
use CoreX\Tasks\Planning\Data\TaskOperationReceipt;
use CoreX\Tasks\Planning\Exceptions\PlanningConflict;
use Illuminate\Database\ConnectionInterface;
use Packman\Attributes\Spi;
use Packman\Attributes\SpiKind;

/** Tasks-owned ledger on the verified owner connection. */
#[Spi(kind: SpiKind::Implement, since: '1.0')]
interface TaskOperationReceipts
{
    /** @throws PlanningConflict If the request key already belongs to a different subject. */
    public function find(ConnectionInterface $connection, TaskOperationIdentity $identity): ?TaskOperationReceipt;

    /** @throws PlanningConflict If the request identity was already recorded. Requires an active transaction. */
    public function record(ConnectionInterface $connection, TaskOperationIdentity $identity, TaskOperationReceipt $receipt): void;
}
