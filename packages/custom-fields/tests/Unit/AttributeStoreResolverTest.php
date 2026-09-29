<?php

declare(strict_types=1);

use CoreX\Config\InvalidConfiguration;
use CoreX\CustomFields\Data\AttributeStoragePolicy;
use CoreX\CustomFields\Internal\Laravel\AttributeComposition;
use CoreX\CustomFields\Internal\Storage\ConfiguredAttributeStoreResolver;
use CoreX\CustomFields\Internal\Storage\ResolvingAttributeStore;
use CoreX\CustomFields\Support\FieldSelection;
use CoreX\CustomFields\Support\RecordSelection;
use CoreX\CustomFields\Support\ValueBag;
use CoreX\CustomFields\Tests\Support\AttributeCompositionProbe;
use CoreX\CustomFields\Tests\Support\RecordingAttributeStore;
use CoreX\Data\RecordRef;

it('rejects an entity map that is not jsonb or eav', function (): void {
    expect(fn () => new AttributeStoragePolicy(default: 'jsonb', entities: ['contact' => 'gin']))
        ->toThrow(InvalidArgumentException::class);
    expect(fn () => AttributeCompositionProbe::from(['storage_by_entity' => ['contact' => ['email' => 'eav']]]))
        ->toThrow(InvalidConfiguration::class);
});

it('rejects a field-level engine declaration before the catalogue is bound', function (): void {
    expect(fn () => AttributeComposition::fromArray([
        'definitions' => [
            'contact' => [['code' => 'email', 'type' => 'string', 'storage_driver' => 'eav']],
        ],
    ]))->toThrow(InvalidConfiguration::class, 'per-field storage driver');
});

it('keeps one engine per entity and restores mixed loadMany order', function (): void {
    $jsonb = new RecordingAttributeStore('jsonb');
    $eav = new RecordingAttributeStore('eav');
    $store = new ResolvingAttributeStore(new ConfiguredAttributeStoreResolver(
        policy: new AttributeStoragePolicy(default: 'jsonb', entities: ['deal' => 'eav']),
        jsonb: $jsonb,
        eav: $eav,
    ));
    $records = [
        new RecordRef(entity: 'contact', id: 'c-1'),
        new RecordRef(entity: 'deal', id: 'd-1'),
        new RecordRef(entity: 'contact', id: 'c-2'),
    ];

    $batch = $store->loadMany(jsonbScope(), new RecordSelection(records: $records), new FieldSelection(fields: ['email']));

    expect(array_map(static fn (ValueBag $bag): string => $bag->values['id'], $batch->records))
        ->toBe(['c-1', 'd-1', 'c-2'])
        ->and(array_map(static fn (ValueBag $bag): string => $bag->values['engine'], $batch->records))
        ->toBe(['jsonb', 'eav', 'jsonb']);
});
