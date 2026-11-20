<?php

declare(strict_types=1);

namespace CoreX\Operations\Idempotency\Data;

use CoreX\Data\Data\DataScope;
use InvalidArgumentException;
use Packman\Attributes\Api;

/**
 * Tenant-local identity included in an idempotency key's collision domain.
 * A principal is (type, id): ids are unique only within a principal type.
 */
#[Api(since: '1.0')]
final readonly class IdempotencyScope
{
    public function __construct(
        public DataScope $scope,
        public ?string $clientId,
        public string $apiMajor,
        public string $operationId,
    ) {
        foreach ([
            'apiMajor' => $apiMajor,
            'operationId' => $operationId,
        ] as $field => $value) {
            if ($value === '' || strlen($value) > 255) {
                throw new InvalidArgumentException("Idempotency scope {$field} must contain 1-255 bytes.");
            }
        }

        if ($clientId !== null && ($clientId === '' || strlen($clientId) > 255)) {
            throw new InvalidArgumentException('Idempotency client ID must be null or contain 1-255 bytes.');
        }
    }

    /** @return array<string, string|null> */
    public function toArray(): array
    {
        return [
            'deployment_id' => $this->scope->deploymentId,
            'account_id' => $this->scope->accountId,
            'account_incarnation' => $this->scope->accountIncarnation,
            'api_major' => $this->apiMajor,
            'client_id' => $this->clientId,
            'operation_id' => $this->operationId,
            'principal_id' => $this->scope->principalId,
            'principal_type' => $this->scope->principalKind,
            'workspace_id' => $this->scope->workspaceId,
        ];
    }
}
