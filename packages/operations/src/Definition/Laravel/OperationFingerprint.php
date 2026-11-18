<?php

declare(strict_types=1);

namespace CoreX\Operations\Definition\Laravel;

use CoreX\Data\Laravel\JsonDocuments;
use CoreX\Operations\Idempotency\Data\IdempotencyScope;
use CoreX\Operations\Invocation\Data\OperationInvocation;
use CoreX\Operations\Validation\Laravel\JsonCanonical;
use Illuminate\Database\Connection;
use Packman\Attributes\Spi;
use Packman\Attributes\SpiKind;

#[Spi(kind: SpiKind::Call, since: '1.0')]
final class OperationFingerprint
{
    public static function request(OperationInvocation $invocation): string
    {
        return hash('sha256', implode("\0", [
            'corex-operation-request/1',
            $invocation->operation->contractHash,
            JsonCanonical::encode($invocation->parameters),
            JsonCanonical::encode(JsonDocuments::map($invocation->input)),
        ]));
    }

    public static function scope(OperationInvocation $invocation, Connection $connection): IdempotencyScope
    {
        $tenant = $invocation->context->tenant();
        $principal = $invocation->context->principal();

        return new IdempotencyScope(
            connectionName: (string) $connection->getName(),
            databaseName: $connection->getDatabaseName(),
            accountId: $tenant->account->id,
            workspaceId: $tenant->workspace?->id,
            principalType: $principal->principalType(),
            principalId: $principal->principalId(),
            clientId: null,
            apiMajor: $invocation->operation->version,
            operationId: $invocation->operation->id,
        );
    }
}
