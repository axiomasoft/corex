<?php

declare(strict_types=1);

use CoreX\Attributes\Contracts\AttributeStore;
use CoreX\Attributes\Data\AttributeStoragePolicy;
use CoreX\Attributes\Internal\Laravel\AttributeComposition;
use CoreX\Attributes\Internal\Storage\ConfiguredAttributeStoreResolver;
use CoreX\Attributes\Internal\Storage\ResolvingAttributeStore;
use CoreX\Attributes\Support\FieldSelection;
use CoreX\Attributes\Support\RecordSelection;
use CoreX\Attributes\Support\ValueBag;
use CoreX\Attributes\Support\ValueBatch;
use CoreX\Attributes\Support\ValuePatch;
use CoreX\Attributes\Support\WriteResult;
use CoreX\Support\Data\DataScope;
use CoreX\Support\Data\RecordRef;
use CoreX\Support\Data\WriteToken;
use CoreX\Support\InvalidConfiguration;

it('rejects an entity map that is not jsonb or eav', function (): void {
    expect(fn () => new AttributeStoragePolicy(default: 'jsonb', entities: ['contact' => 'gin']))
        ->toThrow(InvalidArgumentException::class);
    expect(fn () => AttributeCompositionProbe::from(['storage_by_entity' => ['contact' => ['email' => 'eav']]]))
        ->toThrow(InvalidConfiguration::class);
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

final class RecordingAttributeStore implements AttributeStore
{
    public function __construct(private string $engine) {}

    public function read(DataScope $scope, RecordRef $record, FieldSelection $fields): ValueBag
    {
        return new ValueBag(values: ['engine' => $this->engine, 'id' => $record->id]);
    }

    public function write(DataScope $scope, RecordRef $record, ValuePatch $patch, WriteToken $token): WriteResult
    {
        return new WriteResult(status: 'applied', recordVersion: '1', payloadHash: str_repeat('a', 64));
    }

    public function loadMany(DataScope $scope, RecordSelection $records, FieldSelection $fields): ValueBatch
    {
        return new ValueBatch(records: array_map(
            fn (RecordRef $record): ValueBag => new ValueBag(values: ['engine' => $this->engine, 'id' => $record->id]),
            $records->records,
        ));
    }
}

final class AttributeCompositionProbe
{
    /** @param array<string, mixed> $config */
    public static function from(array $config): void
    {
        AttributeComposition::fromArray($config);
    }
}
