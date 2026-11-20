<?php

declare(strict_types=1);

namespace App\Scenario;

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

final readonly class SearchScenarioProjection implements SearchProjectionContributor, SearchSource
{
    public function __construct(private SearchScenarioTrace $trace) {}

    public function page(DataScope $scope, EntityHandle $entity, ?RecordRef $after, int $limit): RecordBatch
    {
        $rows = SearchScenarioData::rows($scope, $entity);

        if ($after !== null) {
            $rows->where('id', '>', $after->id);
        }

        $records = $rows->orderBy('id')->limit($limit + 1)->pluck('id')->all();
        $keys = array_map(SearchScenarioData::record(...), array_slice($records, 0, $limit));

        return new RecordBatch(new RecordRefs($keys), count($records) > $limit ? end($keys) : null);
    }

    public function load(DataScope $scope, RecordRefs $records): SourceRecords
    {
        $ids = [];
        foreach ($records->records as $record) {
            if ($record->entity !== SearchScenarioRecord::ENTITY) {
                throw new RuntimeException('The source cannot load a different owner entity.');
            }
            $ids[] = $record->id;
        }
        $this->trace->loads[] = ['keys' => $ids, 'workspace' => $scope->workspaceId];
        $rows = SearchScenarioData::rows($scope, SearchScenarioData::entity())->whereIn('id', $ids)->orderBy('id')->get();
        $loaded = $rows->map(static fn (object $row): SourceRecord => new SourceRecord(SearchScenarioData::record($row->id),
            hash('sha256', $row->revision), new ProjectedValues(self::values($row))))->all();

        return new SourceRecords($loaded);
    }

    public function describe(ProjectionContext $context): ProjectionFields
    {
        if ($context->entity->value !== SearchScenarioRecord::ENTITY) {
            return new ProjectionFields([], hash('sha256', 'fixture:no-entity-owner'));
        }

        return new ProjectionFields([
            new ProjectionField('title', FieldType::Text, [FieldUse::Read, FieldUse::Search]),
            new ProjectionField('secret', FieldType::Text, [FieldUse::Read, FieldUse::Search, FieldUse::Filter]),
            new ProjectionField('visible_flag', FieldType::Boolean, [FieldUse::Read, FieldUse::Filter]),
            new ProjectionField('rank', FieldType::Integer, [FieldUse::Read, FieldUse::Filter, FieldUse::Sort, FieldUse::Aggregate]),
        ], hash('sha256', 'fixture:search-record-projection:1'));
    }

    public function project(ProjectionContext $context, RecordRef $record, ProjectionSelection $selection): ProjectedValues
    {
        $row = SearchScenarioData::rows($context->scope, $context->entity)->where('id', $record->id)->first();
        $values = $row === null ? [] : array_intersect_key(self::values($row), array_fill_keys($selection->fields, true));
        $this->trace->pauseProjection($record->id, $values);

        return new ProjectedValues($values);
    }

    private static function values(object $row): array
    {
        return ['title' => $row->title, 'secret' => $row->secret, 'visible_flag' => $row->visible_flag, 'rank' => $row->rank];
    }
}
