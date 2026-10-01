<?php

declare(strict_types=1);

namespace CoreX\Operations\Definition\Laravel;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use CoreX\Data\Laravel\JsonDocuments;
use CoreX\Operations\Idempotency\Data\IdempotencyScope;
use CoreX\Operations\Invocation\Data\OperationInvocation;
use CoreX\Operations\Validation\Laravel\JsonCanonical;
use Illuminate\Database\Connection;

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

        return new IdempotencyScope(
            connectionName: (string) $connection->getName(),
            databaseName: $connection->getDatabaseName(),
            accountId: $tenant->account->id,
            workspaceId: $tenant->workspace?->id,
            principalId: $invocation->context->principal()->id(),
            clientId: null,
            apiMajor: $invocation->operation->version,
            operationId: $invocation->operation->id,
        );
    }
}
