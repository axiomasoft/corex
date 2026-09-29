<?php

declare(strict_types=1);

namespace CoreX\Operations\Definition\Internal;

use CoreX\Operations\Idempotency\Data\IdempotencyScope;
use CoreX\Operations\Invocation\Data\OperationInvocation;
use CoreX\Operations\Validation\JsonCanonical;
use Illuminate\Database\Connection;

/** @internal Builds the stable request identity used by the tenant idempotency store. */
final class OperationFingerprint
{
    public static function request(OperationInvocation $invocation): string
    {
        return hash('sha256', implode("\0", [
            'corex-operation-request/1',
            $invocation->operation->contractHash,
            JsonCanonical::encode($invocation->parameters),
            JsonCanonical::encode($invocation->input),
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
