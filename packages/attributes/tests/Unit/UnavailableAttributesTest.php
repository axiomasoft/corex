<?php

declare(strict_types=1);

use CoreX\Attributes\Internal\UnavailableAttributes;
use CoreX\Attributes\Internal\UnsupportedAttributeCapability;
use CoreX\Attributes\Support\FieldSelection;
use CoreX\Attributes\Support\FieldSnapshot;
use CoreX\Attributes\Support\FilterNode;
use CoreX\Attributes\Support\RecordSelection;
use CoreX\Attributes\Support\Schema\OperationKey;
use CoreX\Attributes\Support\Schema\OperationRef;
use CoreX\Attributes\Support\Schema\SchemaChange;
use CoreX\Attributes\Support\ValuePatch;
use CoreX\Support\Data\DataScope;
use CoreX\Support\Data\RecordRef;
use CoreX\Support\Data\WriteToken;
use CoreX\Tenancy\AccountRef;
use CoreX\Tenancy\TenantContext;

it('fails closed consistently when an application has not enabled attributes', function (): void {
    $hash = str_repeat('a', 64);
    $scope = new DataScope(
        context: new TenantContext(new AccountRef('account-1', 'account-1', 'active', [], []), boxed: true),
        connectionIdentity: 'pgsql:corex_test', actorId: 'user-1', authorize: static fn (): bool => true, restoreEpoch: $hash,
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
        static fn () => $unavailable->request($scope, new SchemaChange('contact', 'add', $hash, ['field' => 'name'], $hash), new OperationKey('schema-1', $hash)),
        static fn () => $unavailable->inspect($scope, new OperationRef('schema-ref-1')),
        static fn () => $unavailable->verify($scope, 'contact'),
    ];

    foreach ($checks as $check) {
        expect($check)->toThrow(UnsupportedAttributeCapability::class);
    }
});
