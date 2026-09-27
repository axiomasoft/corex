<?php

declare(strict_types=1);

namespace CoreX\Tasks\Internal\Planning;

use CoreX\Tasks\Data\PlanningOperation;
use CoreX\Tasks\Data\PlanningToken;
use CoreX\Tasks\Data\TaskOperationKind;
use CoreX\Tasks\Exceptions\PlanningConflict;
use Illuminate\Database\Connection;
use Illuminate\Database\QueryException;

/** @internal Durable planning operation receipts. */
final class OperationStore
{
    public function __construct(private readonly Connection $connection) {}

    /**
     * @param  array{id: string, type: string, display: string}  $resultRef
     * @return array{id: string, type: string, display: string}|null
     */
    public function claimOrReplay(
        PlanningOperation $operation,
        PlanningToken $token,
        string $restoreEpoch,
        ?array $resultRef = null,
    ): ?array {
        if ($resultRef !== null) {
            $this->insert(
                operation: $operation,
                token: $token,
                restoreEpoch: $restoreEpoch,
                resultRef: $resultRef,
            );

            return null;
        }

        $existing = (new DatabaseTaskOperationReceipts)->find($this->connection, $token->requestKey);

        if ($existing === null) {
            return null;
        }

        if (! hash_equals($existing->payloadHash, $token->payloadHash)) {
            throw PlanningConflict::forRequestKey($token->requestKey);
        }

        /** @var array{id: string, type: string, display: string} */
        return $existing->resultRef;
    }

    /** @param array{id: string, type: string, display: string} $resultRef */
    private function insert(
        PlanningOperation $operation,
        PlanningToken $token,
        string $restoreEpoch,
        array $resultRef,
    ): void {
        try {
            (new DatabaseTaskOperationReceipts)->record(
                connection: $this->connection,
                kind: TaskOperationKind::from($operation->value),
                requestKey: $token->requestKey,
                payloadHash: $token->payloadHash,
                restoreEpoch: $restoreEpoch,
                resultRef: $resultRef,
            );
        } catch (QueryException $exception) {
            $existing = (new DatabaseTaskOperationReceipts)->find($this->connection, $token->requestKey);

            if ($existing === null) {
                throw $exception;
            }

            if (! hash_equals($existing->payloadHash, $token->payloadHash)) {
                throw PlanningConflict::forRequestKey($token->requestKey);
            }
        }
    }
}
