<?php

declare(strict_types=1);

namespace CoreX\Tasks\Internal\Planning;

use CoreX\Tasks\Data\PlanningOperation;
use CoreX\Tasks\Data\PlanningToken;
use CoreX\Tasks\Exceptions\PlanningConflict;
use Illuminate\Database\Connection;
use Illuminate\Database\QueryException;
use Illuminate\Support\Str;

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

        $existing = $this->connection
            ->table('corex_task_operations')
            ->where('request_key', $token->requestKey)
            ->first();

        if ($existing === null) {
            return null;
        }

        if (! hash_equals((string) $existing->payload_hash, $token->payloadHash)) {
            throw PlanningConflict::forRequestKey($token->requestKey);
        }

        /** @var array{id: string, type: string, display: string} */
        return json_decode((string) $existing->result_ref, true, flags: JSON_THROW_ON_ERROR);
    }

    /** @param array{id: string, type: string, display: string} $resultRef */
    private function insert(
        PlanningOperation $operation,
        PlanningToken $token,
        string $restoreEpoch,
        array $resultRef,
    ): void {
        try {
            $this->connection->table('corex_task_operations')->insert([
                'id' => (string) Str::uuid7(),
                'request_key' => $token->requestKey,
                'payload_hash' => $token->payloadHash,
                'kind' => $operation->value,
                'result_ref' => json_encode($resultRef, JSON_THROW_ON_ERROR),
                'restore_epoch' => $restoreEpoch,
                'created_at' => now(),
            ]);
        } catch (QueryException $exception) {
            $existing = $this->connection
                ->table('corex_task_operations')
                ->where('request_key', $token->requestKey)
                ->first();

            if ($existing === null) {
                throw $exception;
            }

            if (! hash_equals((string) $existing->payload_hash, $token->payloadHash)) {
                throw PlanningConflict::forRequestKey($token->requestKey);
            }
        }
    }
}
