<?php

declare(strict_types=1);

namespace CoreX\Operations\Idempotency\Data;

use CoreX\Attributes\Api;
use InvalidArgumentException;

/** Tenant-local identity included in an idempotency key's collision domain. */
#[Api(since: '1.0')]
final readonly class IdempotencyScope
{
    public function __construct(
        public string $connectionName,
        public string $databaseName,
        public string $accountId,
        public ?string $workspaceId,
        public string $principalId,
        public ?string $clientId,
        public string $apiMajor,
        public string $operationId,
    ) {
        foreach ([
            'connectionName' => $connectionName,
            'databaseName' => $databaseName,
            'accountId' => $accountId,
            'principalId' => $principalId,
            'apiMajor' => $apiMajor,
            'operationId' => $operationId,
        ] as $field => $value) {
            if ($value === '' || strlen($value) > 255) {
                throw new InvalidArgumentException("Idempotency scope {$field} must contain 1-255 bytes.");
            }
        }

        foreach (['workspaceId' => $workspaceId, 'clientId' => $clientId] as $field => $value) {
            if ($value !== null && ($value === '' || strlen($value) > 255)) {
                throw new InvalidArgumentException("Idempotency scope {$field} must be null or contain 1-255 bytes.");
            }
        }
    }

    /** @return array<string, string|null> */
    public function toArray(): array
    {
        return [
            'account_id' => $this->accountId,
            'api_major' => $this->apiMajor,
            'client_id' => $this->clientId,
            'connection_name' => $this->connectionName,
            'database_name' => $this->databaseName,
            'operation_id' => $this->operationId,
            'principal_id' => $this->principalId,
            'workspace_id' => $this->workspaceId,
        ];
    }
}
