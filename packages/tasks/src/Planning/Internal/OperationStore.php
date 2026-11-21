<?php

declare(strict_types=1);

namespace CoreX\Tasks\Planning\Internal;

use CoreX\Data\Laravel\JsonDocuments;
use CoreX\Tasks\Planning\Contracts\TaskOperationReceipts;
use CoreX\Tasks\Planning\Data\TaskOperationFence;
use CoreX\Tasks\Planning\Data\TaskOperationIdentity;
use CoreX\Tasks\Planning\Data\TaskOperationReceipt;
use CoreX\Tasks\Planning\Exceptions\PlanningConflict;
use Illuminate\Database\ConnectionInterface;
use Packman\Attributes\Internal;

/** @internal Durable planning operation receipts. */
#[Internal]
final readonly class OperationStore
{
    public function __construct(
        private ConnectionInterface $connection,
        private TaskOperationReceipts $receipts,
    ) {}

    /** @return array{id: string, type: string, display: string}|null */
    public function replay(TaskOperationIdentity $identity, string $payloadHash): ?array
    {
        $existing = $this->receipts->find(connection: $this->connection, identity: $identity);

        if ($existing === null) {
            return null;
        }

        if (! hash_equals($existing->payloadHash, $payloadHash)) {
            throw PlanningConflict::forRequestKey($identity->requestKey);
        }

        /** @var array{id: string, type: string, display: string} */
        return JsonDocuments::map($existing->resultRef);
    }

    /** @param array{id: string, type: string, display: string} $resultRef */
    public function record(TaskOperationIdentity $identity, string $payloadHash, array $resultRef): void
    {
        $this->receipts->record(
            connection: $this->connection,
            identity: $identity,
            receipt: new TaskOperationReceipt(
                payloadHash: $payloadHash,
                originalFence: TaskOperationFence::fromScope($identity->scope),
                resultRef: JsonDocuments::capture($resultRef),
            ),
        );
    }
}
