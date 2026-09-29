<?php

declare(strict_types=1);

use CoreX\Context\Data\AccountRef;
use CoreX\Context\Data\TenantContext;
use CoreX\CustomFields\Internal\UnavailableAttributes;
use CoreX\CustomFields\Internal\UnsupportedAttributeCapability;
use CoreX\CustomFields\Support\FieldSelection;
use CoreX\CustomFields\Support\FieldSnapshot;
use CoreX\CustomFields\Support\FilterNode;
use CoreX\CustomFields\Support\RecordSelection;
use CoreX\CustomFields\Support\Schema\OperationRef;
use CoreX\CustomFields\Support\Schema\SchemaChange;
use CoreX\CustomFields\Support\Schema\SchemaOperationId;
use CoreX\CustomFields\Support\ValuePatch;
use CoreX\Data\DataScope;
use CoreX\Data\RecordRef;
use CoreX\Data\WriteToken;
use CoreX\Errors\Enums\ErrorCategory;
use CoreX\Errors\Enums\UnavailabilityReason;

it('fails closed consistently when an application has not enabled attributes', function (): void {
    $hash = str_repeat('a', 64);
    $scope = DataScope::fromTenantContext(
        context: new TenantContext(new AccountRef('account-1', 'account-1', 'active', [], []), boxed: true),
        connectionIdentity: 'pgsql:corex_test', actorId: 'user-1', restoreEpoch: $hash,
    );
    $record = new RecordRef(entity: 'contact', id: 'record-1');
    $schema = new FieldSnapshot(entity: 'contact', schemaVersion: $hash, schemaHash: $hash, activeFields: [['code' => 'name', 'type' => 'text']], driverCapabilities: []);
    $unavailable = new UnavailableAttributes;
    $checks = [
        static fn () => $unavailable->snapshot($scope, 'contact'),
        static fn () => $unavailable->read($scope, $record, new FieldSelection(['name'])),
        static fn () => $unavailable->write($scope, $record, new ValuePatch(['name' => 'Ada']), new WriteToken('write-1', $hash, 'absent')),
        static fn () => $unavailable->loadMany($scope, new RecordSelection([$record]), new FieldSelection(['name'])),
        static fn () => $unavailable->compile($scope, $schema, new FilterNode(['op' => 'eq', 'field' => 'name', 'value' => 'Ada'])),
        static fn () => $unavailable->request($scope, new SchemaChange('contact', 'add', $hash, ['field' => 'name'], $hash), new SchemaOperationId('schema-1', $hash)),
        static fn () => $unavailable->inspect($scope, new OperationRef('schema-ref-1')),
        static fn () => $unavailable->verify($scope, 'contact'),
    ];

    foreach ($checks as $check) {
        expect($check)->toThrow(UnsupportedAttributeCapability::class);
    }

    $descriptor = (new UnsupportedAttributeCapability)->errorDescriptor();

    expect($descriptor->code)->toBe('attributes_engine_unavailable')
        ->and($descriptor->category)->toBe(ErrorCategory::Unavailable)
        ->and($descriptor->retryable)->toBeFalse()
        ->and($descriptor->details)->toBe(['reason' => UnavailabilityReason::CapabilityUnavailable->value]);
});
