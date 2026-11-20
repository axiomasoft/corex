<?php

declare(strict_types=1);

use CoreX\Context\Data\AccountRef;
use CoreX\Context\Data\TenantContext;
use CoreX\CustomFields\Contracts\FieldCatalogue;
use CoreX\CustomFields\Data\AttributeId;
use CoreX\CustomFields\Data\FieldSnapshot;
use CoreX\Data\Contracts\DataScopeAuthorizer;
use CoreX\Data\Data\DataScope;
use CoreX\Data\Data\FieldSelection;
use CoreX\Data\Internal\CallableDataScopeAuthorizer;
use CoreX\Tests\Support\CallerWorkScopeFixture;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;

function jsonbScope(?string $bindingId = null, ?string $restoreEpoch = null): DataScope
{
    return CallerWorkScopeFixture::forContext(jsonbContext(), 'user-1', $restoreEpoch ?? str_repeat('a', 64), $bindingId);
}

function jsonbContext(): TenantContext
{
    return new TenantContext(new AccountRef('account-1', 'account-1', 'active', [], []), boxed: true);
}

function jsonbCurrent(): CallerWorkScopeFixture
{
    return new CallerWorkScopeFixture(jsonbContext(), static fn (): ConnectionInterface => DB::connection(), jsonbScope());
}

/** @param list<string> $codes */
function jsonbSelection(string $entity, array $codes): FieldSelection
{
    return new FieldSelection(array_map(static fn (string $code): string => AttributeId::legacy($entity, $code)->value, $codes));
}

function jsonbAuthorizer(?Closure $authorize = null): DataScopeAuthorizer
{
    if ($authorize === null) {
        return new CallableDataScopeAuthorizer(static fn (): bool => true);
    }

    return new CallableDataScopeAuthorizer($authorize);
}

function schemaCatalogue(string $entity, string $schemaHash): FieldCatalogue
{
    $snapshot = new FieldSnapshot($entity, $schemaHash, $schemaHash, [], []);

    return new readonly class($snapshot) implements FieldCatalogue
    {
        public function __construct(private FieldSnapshot $snapshot) {}

        public function snapshot(DataScope $scope, string $entity): FieldSnapshot
        {
            return $this->snapshot;
        }
    };
}
