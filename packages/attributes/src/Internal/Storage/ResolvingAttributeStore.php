<?php

declare(strict_types=1);

namespace CoreX\Attributes\Internal\Storage;

use CoreX\Attributes\Contracts\AttributeStore;
use CoreX\Attributes\Contracts\AttributeStoreResolver;
use CoreX\Attributes\Support\FieldSelection;
use CoreX\Attributes\Support\RecordSelection;
use CoreX\Attributes\Support\ValueBag;
use CoreX\Attributes\Support\ValueBatch;
use CoreX\Attributes\Support\ValuePatch;
use CoreX\Attributes\Support\WriteResult;
use CoreX\Support\Data\DataScope;
use CoreX\Support\Data\RecordRef;
use CoreX\Support\Data\WriteToken;

final readonly class ResolvingAttributeStore implements AttributeStore
{
    public function __construct(private AttributeStoreResolver $resolver) {}

    public function read(DataScope $scope, RecordRef $record, FieldSelection $fields): ValueBag
    {
        return $this->resolver->forEntity($record->entity)->read($scope, $record, $fields);
    }

    public function write(DataScope $scope, RecordRef $record, ValuePatch $patch, WriteToken $token): WriteResult
    {
        return $this->resolver->forEntity($record->entity)->write($scope, $record, $patch, $token);
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
