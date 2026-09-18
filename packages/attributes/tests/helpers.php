<?php

declare(strict_types=1);

use CoreX\Attributes\Contracts\FieldCatalogue;
use CoreX\Attributes\Support\FieldSnapshot;
use CoreX\Contracts\DataScopeAuthorizer;
use CoreX\Support\Data\AllowAllDataScopeAuthorizer;
use CoreX\Support\Data\CallableDataScopeAuthorizer;
use CoreX\Support\Data\DataScope;
use CoreX\Tenancy\AccountRef;
use CoreX\Tenancy\TenantContext;

function jsonbScope(string $connectionIdentity = 'pgsql:corex_test', ?string $restoreEpoch = null): DataScope
{
    return new DataScope(
        context: new TenantContext(new AccountRef('account-1', 'account-1', 'active', [], []), boxed: true),
        connectionIdentity: $connectionIdentity,
        actorId: 'user-1',
        restoreEpoch: $restoreEpoch ?? str_repeat('a', 64),
    );
}

function jsonbAuthorizer(?Closure $authorize = null): DataScopeAuthorizer
{
    if ($authorize === null) {
        return new AllowAllDataScopeAuthorizer;
    }

    return new CallableDataScopeAuthorizer($authorize);
}

function schemaCatalogue(string $entity, string $schemaHash): FieldCatalogue
{
    $snapshot = new FieldSnapshot($entity, $schemaHash, $schemaHash, [], []);

    return new class($snapshot) implements FieldCatalogue
    {
        public function __construct(private FieldSnapshot $snapshot) {}

        public function snapshot(DataScope $scope, string $entity): FieldSnapshot
        {
            return $this->snapshot;
        }
    };
}
