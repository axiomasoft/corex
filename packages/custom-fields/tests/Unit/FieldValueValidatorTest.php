<?php

declare(strict_types=1);

use CoreX\Context\Data\AccountRef;
use CoreX\Context\Data\TenantContext;
use CoreX\CustomFields\Contracts\FieldCatalogue;
use CoreX\CustomFields\Data\FieldSnapshot;
use CoreX\CustomFields\Data\ValuePatch;
use CoreX\CustomFields\Internal\InvalidAttributeValue;
use CoreX\CustomFields\Internal\Values\FieldValueValidator;
use CoreX\Data\Data\DataScope;
use CoreX\Data\Data\RecordRef;

it('accepts all supported typed values from the active registry snapshot', function (): void {
    $hash = str_repeat('a', 64);
    $fields = [
        ['code' => 'text_value', 'type' => 'text'], ['code' => 'int_value', 'type' => 'int'],
        ['code' => 'decimal_value', 'type' => 'decimal'], ['code' => 'bool_value', 'type' => 'bool'],
        ['code' => 'date_value', 'type' => 'date'], ['code' => 'datetime_value', 'type' => 'datetime'],
        ['code' => 'select_value', 'type' => 'select'], ['code' => 'multiselect_value', 'type' => 'multiselect'],
        ['code' => 'reference_value', 'type' => 'reference'], ['code' => 'money_value', 'type' => 'money'],
        ['code' => 'url_value', 'type' => 'url'], ['code' => 'email_value', 'type' => 'email'],
        ['code' => 'phone_value', 'type' => 'phone'], ['code' => 'file_value', 'type' => 'file'],
        ['code' => 'json_value', 'type' => 'json'],
    ];
    $catalogue = new class(new FieldSnapshot('contact', $hash, $hash, $fields, [])) implements FieldCatalogue
    {
        public function __construct(private FieldSnapshot $snapshot) {}

        public function snapshot(DataScope $scope, string $entity): FieldSnapshot
        {
            return $this->snapshot;
        }
    };
    $scope = DataScope::fromTenantContext(
        context: new TenantContext(new AccountRef('account-1', 'account-1', 'active', [], []), boxed: true),
        connectionIdentity: 'pgsql:corex_test', actorId: 'user-1', restoreEpoch: $hash,
    );

    (new FieldValueValidator($catalogue))->validatePatch(
        scope: $scope,
        record: new RecordRef(entity: 'contact', id: 'record-1'),
        patch: new ValuePatch(values: [
            'text_value' => 'text', 'int_value' => 2, 'decimal_value' => '2.50', 'bool_value' => true,
            'date_value' => '2026-09-16', 'datetime_value' => '2026-09-16T12:00:00+00:00', 'select_value' => 'open',
            'multiselect_value' => ['one', 'two'], 'reference_value' => 'record-2', 'money_value' => ['minor' => 250, 'currency' => 'USD'],
            'url_value' => 'https://example.test', 'email_value' => 'owner@example.test', 'phone_value' => '+1 555 010 0200',
            'file_value' => 'file-1', 'json_value' => ['nested' => true],
        ]),
    );

    expect(true)->toBeTrue();
});

it('rejects an unknown field and floating point money values', function (): void {
    $hash = str_repeat('a', 64);
    $catalogue = new class(new FieldSnapshot('contact', $hash, $hash, [['code' => 'money', 'type' => 'money']], [])) implements FieldCatalogue
    {
        public function __construct(private FieldSnapshot $snapshot) {}

        public function snapshot(DataScope $scope, string $entity): FieldSnapshot
        {
            return $this->snapshot;
        }
    };
    $scope = DataScope::fromTenantContext(
        context: new TenantContext(new AccountRef('account-1', 'account-1', 'active', [], []), boxed: true),
        connectionIdentity: 'pgsql:corex_test', actorId: 'user-1', restoreEpoch: $hash,
    );
    $validator = new FieldValueValidator($catalogue);
    $record = new RecordRef(entity: 'contact', id: 'record-1');

    expect(fn (): mixed => $validator->validatePatch($scope, $record, new ValuePatch(['unknown' => 'x'])))
        ->toThrow(InvalidAttributeValue::class);
    expect(fn (): mixed => $validator->validatePatch($scope, $record, new ValuePatch(['money' => ['minor' => 1.5, 'currency' => 'USD']])))
        ->toThrow(InvalidArgumentException::class);
});
