<?php

declare(strict_types=1);

namespace App\FieldSearch;

use CoreX\Data\Data\DataScope;
use CoreX\Data\Data\EntityHandle;
use CoreX\Data\Data\RecordRef;
use CoreX\Data\Enums\FieldUse;
use CoreX\Search\Projection\Contracts\SearchProjectionContributor;
use CoreX\Search\Projection\Contracts\SearchSource;
use CoreX\Search\Projection\Data\ProjectedValues;
use CoreX\Search\Projection\Data\ProjectionContext;
use CoreX\Search\Projection\Data\ProjectionField;
use CoreX\Search\Projection\Data\ProjectionFields;
use CoreX\Search\Projection\Data\ProjectionSelection;
use CoreX\Search\Projection\Data\RecordBatch;
use CoreX\Search\Projection\Data\RecordRefs;
use CoreX\Search\Projection\Data\SourceRecord;
use CoreX\Search\Projection\Data\SourceRecords;
use CoreX\Search\Projection\Enums\FieldType;
use RuntimeException;

final class EquipmentSource implements SearchProjectionContributor, SearchSource
{
    public function page(DataScope $scope, EntityHandle $entity, ?RecordRef $after, int $limit): RecordBatch
    {
        if ($entity->value !== EquipmentOwner::ENTITY) {
            throw new RuntimeException('The fixture owns only the installed equipment entity.');
        }
        $query = EquipmentOwner::records($scope);

        if ($after !== null) {
            $query->where('asset_key', '>', $after->id);
        }
        $ids = $query->orderBy('asset_key')->limit($limit + 1)->pluck('asset_key');
        $records = $ids->take($limit)->map(EquipmentOwner::record(...))->all();

        return new RecordBatch(new RecordRefs($records), $ids->count() > $limit ? end($records) : null);
    }

    public function load(DataScope $scope, RecordRefs $records): SourceRecords
    {
        $ids = [];
        foreach ($records->records as $record) {
            if ($record->entity !== EquipmentOwner::ENTITY) {
                throw new RuntimeException('The installed equipment source received a foreign entity.');
            }
            $ids[] = $record->id;
        }
        $rows = EquipmentOwner::records($scope)->whereIn('asset_key', $ids)->get();
        $result = $rows->map(static fn (object $row): SourceRecord => new SourceRecord(
            EquipmentOwner::record($row->asset_key), hash('sha256', json_encode((array) $row, JSON_THROW_ON_ERROR)),
            new ProjectedValues(['title' => $row->name]),
        ))->all();

        return new SourceRecords($result);
    }

    public function describe(ProjectionContext $context): ProjectionFields
    {
        return new ProjectionFields($context->entity->value === EquipmentOwner::ENTITY
            ? [new ProjectionField('title', FieldType::Text, [FieldUse::Read, FieldUse::Search])] : [],
            hash('sha256', 'equipment-owner:title:v1'));
    }

    public function project(ProjectionContext $context, RecordRef $record, ProjectionSelection $selection): ProjectedValues
    {
        if ($record->entity !== EquipmentOwner::ENTITY || ! in_array('title', $selection->fields, true)) {
            return new ProjectedValues([]);
        }
        $value = EquipmentOwner::records($context->scope)->where('asset_key', $record->id)->value('name');

        return new ProjectedValues(is_string($value) ? ['title' => $value] : []);
    }
}
