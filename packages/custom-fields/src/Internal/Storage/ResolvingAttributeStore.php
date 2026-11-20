<?php

declare(strict_types=1);

namespace CoreX\CustomFields\Internal\Storage;

use CoreX\CustomFields\Contracts\AttributeStore;
use CoreX\CustomFields\Contracts\AttributeStoreResolver;
use CoreX\CustomFields\Data\AttributeWriteResult;
use CoreX\CustomFields\Data\RecordSelection;
use CoreX\CustomFields\Data\ValueBag;
use CoreX\CustomFields\Data\ValueBatch;
use CoreX\CustomFields\Data\ValuePatch;
use CoreX\Data\Data\DataScope;
use CoreX\Data\Data\FieldSelection;
use CoreX\Data\Data\RecordRef;
use CoreX\Data\Data\WriteToken;
use Packman\Attributes\Internal;

#[Internal]
final readonly class ResolvingAttributeStore implements AttributeStore
{
    public function __construct(private AttributeStoreResolver $resolver) {}

    public function read(DataScope $scope, RecordRef $record, FieldSelection $fields): ValueBag
    {
        return $this->resolver->forEntity($record->entity)->read(scope: $scope, record: $record, fields: $fields);
    }

    public function write(DataScope $scope, RecordRef $record, ValuePatch $patch, WriteToken $token): AttributeWriteResult
    {
        return $this->resolver->forEntity($record->entity)->write(scope: $scope, record: $record, patch: $patch, token: $token);
    }

    public function loadMany(DataScope $scope, RecordSelection $records, FieldSelection $fields): ValueBatch
    {
        /** @var array<int, array{store: AttributeStore, items: list<array{0: int, 1: RecordRef}>}> $groups */
        $groups = [];

        foreach ($records->records as $index => $record) {
            $store = $this->resolver->forEntity($record->entity);
            $key = spl_object_id($store);
            $groups[$key]['store'] = $store;
            $groups[$key]['items'][] = [$index, $record];
        }

        $bags = array_fill(0, count($records->records), null);

        foreach ($groups as $group) {
            $selection = new RecordSelection(records: array_map(
                static fn (array $item): RecordRef => $item[1],
                $group['items'],
            ));
            $batch = $group['store']->loadMany($scope, $selection, $fields);

            foreach ($group['items'] as $offset => $item) {
                $bags[$item[0]] = $batch->records[$offset];
            }
        }

        /** @var list<ValueBag> $bags */
        return new ValueBatch(records: $bags);
    }
}
