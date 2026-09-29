<?php

declare(strict_types=1);

use CoreX\Context\Contracts\TenantContextRestorer;
use CoreX\Context\Data\AccountRef;
use CoreX\Context\Data\TenantContext;
use CoreX\CustomFields\Contracts\FieldCatalogue;
use CoreX\CustomFields\Support\FieldSnapshot;
use CoreX\Data\AllowAllDataScopeAuthorizer;
use CoreX\Data\CallableDataScopeAuthorizer;
use CoreX\Data\Contracts\DataScopeAuthorizer;
use CoreX\Data\DataScope;

function jsonbScope(string $connectionIdentity = 'pgsql:corex_test', ?string $restoreEpoch = null): DataScope
{
    return DataScope::fromTenantContext(
        context: new TenantContext(new AccountRef('account-1', 'account-1', 'active', [], []), boxed: true),
        connectionIdentity: $connectionIdentity,
        actorId: 'user-1',
        restoreEpoch: $restoreEpoch ?? str_repeat('a', 64),
    );
}

function fixedTenantContextRestorer(TenantContext $context): TenantContextRestorer
{
    return new class($context) implements TenantContextRestorer
    {
        public function __construct(private TenantContext $context) {}

        public function restore(DataScope $scope): TenantContext
        {
            return $this->context;
        }
    };
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
