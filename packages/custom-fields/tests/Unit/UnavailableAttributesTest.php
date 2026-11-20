<?php

declare(strict_types=1);

use CoreX\Context\Data\AccountRef;
use CoreX\Context\Data\TenantContext;
use CoreX\CustomFields\Data\FieldSnapshot;
use CoreX\CustomFields\Data\FilterNode;
use CoreX\CustomFields\Data\OperationRef;
use CoreX\CustomFields\Data\RecordSelection;
use CoreX\CustomFields\Data\SchemaChange;
use CoreX\CustomFields\Data\SchemaOperationId;
use CoreX\CustomFields\Data\ValuePatch;
use CoreX\CustomFields\Internal\UnavailableAttributes;
use CoreX\CustomFields\Internal\UnsupportedAttributeCapability;
use CoreX\Data\Data\FieldSelection;
use CoreX\Data\Data\RecordRef;
use CoreX\Data\Data\WriteToken;
use CoreX\Tests\Support\CallerWorkScopeFixture;
use Packman\Errors\Enums\ErrorCategory;
use Packman\Errors\Enums\UnavailabilityReason;

it('fails closed consistently when an application has not enabled attributes', function (): void {
    $hash = str_repeat('a', 64);
    $scope = CallerWorkScopeFixture::forContext(new TenantContext(new AccountRef('account-1', 'account-1', 'active', [], []), boxed: true), 'user-1', $hash);
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

    expect($descriptor->code)->toBe('custom-fields.attributes_engine_unavailable')
        ->and($descriptor->category)->toBe(ErrorCategory::Unavailable)
        ->and($descriptor->retryable)->toBeFalse()
        ->and($descriptor->details)->toBe(['reason' => UnavailabilityReason::CapabilityUnavailable->value]);
});
