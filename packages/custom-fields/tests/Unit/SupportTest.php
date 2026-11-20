<?php

declare(strict_types=1);

use CoreX\Context\Data\AccountRef;
use CoreX\Context\Data\TenantContext;
use CoreX\CustomFields\Data\FieldSnapshot;
use CoreX\CustomFields\Data\FilterNode;
use CoreX\CustomFields\Data\RecordSelection;
use CoreX\CustomFields\Data\SchemaChange;
use CoreX\CustomFields\Data\ValuePatch;
use CoreX\CustomFields\Internal\Query\SchemaAttributeFilter;
use CoreX\CustomFields\Internal\UnavailableAttributes;
use CoreX\CustomFields\Internal\UnsupportedAttributeCapability;
use CoreX\Data\Contracts\FieldUsePolicy;
use CoreX\Data\Data\DataScope;
use CoreX\Data\Data\EntityHandle;
use CoreX\Data\Data\FieldSelection;
use CoreX\Data\Data\FieldUsePlan;
use CoreX\Data\Data\FieldUseRequest;
use CoreX\Data\Data\RecordRef;
use CoreX\Tests\Support\CallerWorkScopeFixture;

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
    $scope = CallerWorkScopeFixture::forContext(new TenantContext(new AccountRef('account-1', 'account-1', 'active', [], []), boxed: true), 'user-1', str_repeat('a', 64));

    expect(fn () => (new UnavailableAttributes)->snapshot($scope, 'contact'))
        ->toThrow(UnsupportedAttributeCapability::class);
});

it('compiles only structural allowlisted filters', function (): void {
    $hash = str_repeat('a', 64);
    $schema = new FieldSnapshot('contact', 'v1', $hash, [['code' => 'email', 'type' => 'email']], []);
    $scope = CallerWorkScopeFixture::forContext(new TenantContext(new AccountRef('account-1', 'account-1', 'active', [], []), boxed: true), 'user-1', $hash);
    $filter = new SchemaAttributeFilter(new class implements FieldUsePolicy
    {
        public function plan(DataScope $scope, EntityHandle $entity, FieldUseRequest $request): FieldUsePlan
        {
            return FieldUsePlan::unconditional('fixture-filter-policy');
        }
    }, new CallerWorkScopeFixture(jsonbContext(), expected: $scope));

    expect($filter->compile($scope, $schema, new FilterNode(['op' => 'eq', 'field' => 'email', 'value' => 'a@example.test']))->schemaHash)->toBe($hash);

    expect(fn () => $filter->compile($scope, $schema, new FilterNode(['op' => 'eq', 'field' => 'unknown', 'value' => 'x'])))->toThrow(InvalidArgumentException::class);
});
