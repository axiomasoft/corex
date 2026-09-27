<?php

declare(strict_types=1);

use CoreX\Attributes\Internal\Query\SchemaAttributeFilter;
use CoreX\Attributes\Internal\UnavailableAttributes;
use CoreX\Attributes\Internal\UnsupportedAttributeCapability;
use CoreX\Attributes\Support\FieldSelection;
use CoreX\Attributes\Support\FieldSnapshot;
use CoreX\Attributes\Support\FilterNode;
use CoreX\Attributes\Support\RecordSelection;
use CoreX\Attributes\Support\Schema\SchemaChange;
use CoreX\Attributes\Support\ValuePatch;
use CoreX\Support\Data\AllowAllDataScopeAuthorizer;
use CoreX\Support\Data\DataScope;
use CoreX\Support\Data\RecordRef;
use CoreX\Tenancy\AccountRef;
use CoreX\Tenancy\TenantContext;

it('keeps field selections bounded and unique', function (): void {
    expect((new FieldSelection(fields: ['email', 'status']))->fields)->toBe(['email', 'status']);

    expect(fn (): FieldSelection => new FieldSelection(fields: ['email', 'email']))
        ->toThrow(InvalidArgumentException::class);
});

it('keeps record selections bounded and ordered', function (): void {
    $records = new RecordSelection(records: [
        new RecordRef(entity: 'contact', id: 'first'),
        new RecordRef(entity: 'contact', id: 'second'),
    ]);

    expect($records->records[1]->id)->toBe('second');

    expect(fn (): RecordSelection => new RecordSelection(records: [
        new RecordRef(entity: 'contact', id: 'first'),
        new RecordRef(entity: 'contact', id: 'first'),
    ]))->toThrow(InvalidArgumentException::class);
});

it('accepts only canonical schema-bound attribute data', function (): void {
    $hash = str_repeat('a', 64);
    $snapshot = new FieldSnapshot(
        entity: 'contact',
        schemaVersion: 'v1',
        schemaHash: $hash,
        activeFields: [['code' => 'email', 'type' => 'email']],
        driverCapabilities: ['jsonb' => true],
    );

    expect($snapshot->schemaHash)->toBe($hash)
        ->and((new ValuePatch(values: ['email' => null]))->values)->toBe(['email' => null]);
});

it('rejects unapproved or oversized schema-change inputs', function (): void {
    $hash = str_repeat('b', 64);

    expect(fn (): SchemaChange => new SchemaChange(
        entity: 'contact',
        kind: 'alter table',
        expectedSchemaHash: $hash,
        definition: ['column' => 'email'],
        approvalDigest: $hash,
    ))->toThrow(InvalidArgumentException::class);

    expect(fn (): ValuePatch => new ValuePatch(values: ['email' => 1.5]))
        ->toThrow(InvalidArgumentException::class);
});

it('fails closed while no qualified engine is bound', function (): void {
    $scope = DataScope::fromTenantContext(
        context: new TenantContext(new AccountRef('account-1', 'account-1', 'active', [], []), boxed: true),
        connectionIdentity: 'boxed:account-1',
        actorId: 'user-1',
        restoreEpoch: str_repeat('a', 64),
    );

    expect(fn () => (new UnavailableAttributes)->snapshot($scope, 'contact'))
        ->toThrow(UnsupportedAttributeCapability::class);
});

it('compiles only structural allowlisted filters', function (): void {
    $hash = str_repeat('a', 64);
    $schema = new FieldSnapshot('contact', 'v1', $hash, [['code' => 'email', 'type' => 'email']], []);
    $scope = DataScope::fromTenantContext(new TenantContext(new AccountRef('account-1', 'account-1', 'active', [], []), boxed: true), 'boxed:account-1', 'user-1', $hash);
    $filter = new SchemaAttributeFilter(new AllowAllDataScopeAuthorizer);

    expect($filter->compile($scope, $schema, new FilterNode(['op' => 'eq', 'field' => 'email', 'value' => 'a@example.test']))->schemaHash)->toBe($hash);

    expect(fn () => $filter->compile($scope, $schema, new FilterNode(['op' => 'eq', 'field' => 'unknown', 'value' => 'x'])))->toThrow(InvalidArgumentException::class);
});
