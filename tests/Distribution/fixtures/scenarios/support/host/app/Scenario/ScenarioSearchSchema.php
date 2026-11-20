<?php

declare(strict_types=1);

namespace App\Scenario;

use CoreX\Context\Contracts\CurrentWorkScope;
use CoreX\Data\Data\DataScope;
use CoreX\Data\Data\EntityHandle;
use CoreX\Data\Data\RecordRef;
use CoreX\Data\Enums\FieldUse;
use CoreX\Search\Index\Data\IndexTarget;
use CoreX\Search\Postgres\Contracts\PostgresCorpus;
use CoreX\Search\Postgres\Data\PostgresCorpusRows;
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
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class ScenarioSearchSchema implements PostgresCorpus, SearchProjectionContributor, SearchSource
{
    public const string ENTITY = 'scenario.equipment';

    public static function entity(): EntityHandle
    {
        return new EntityHandle(self::ENTITY);
    }

    public function describe(ProjectionContext $context): ProjectionFields
    {
        if ($context->entity->value !== self::ENTITY) {
            return new ProjectionFields([], hash('sha256', 'unavailable'));
        }

        return new ProjectionFields([
            new ProjectionField('marker', FieldType::Text, [FieldUse::Search]),
            new ProjectionField('tenant_a_label', FieldType::Text, [FieldUse::Read]),
            new ProjectionField('tenant_b_label', FieldType::Text, [FieldUse::Read]),
        ], hash('sha256', 'queue-scenario-owner-fields'));
    }

    public function project(ProjectionContext $context, RecordRef $record, ProjectionSelection $selection): ProjectedValues
    {
        $this->verify($context->scope, $context->entity, DB::connection());
        $row = DB::table('scenario_records')->where('id', $record->id)->first();

        return new ProjectedValues(array_intersect_key((array) $row, array_fill_keys($selection->fields, true)));
    }

    public function page(DataScope $scope, EntityHandle $entity, ?RecordRef $after, int $limit): RecordBatch
    {
        $this->verify($scope, $entity, DB::connection());
        $query = DB::table('scenario_records');

        if ($after !== null) {
            $query->where('id', '>', $after->id);
        }
        $ids = $query->orderBy('id')->limit($limit + 1)->pluck('id');
        $refs = $ids->take($limit)->map(static fn (string $id): RecordRef => new RecordRef(self::ENTITY, $id))->all();

        return new RecordBatch(new RecordRefs($refs), $ids->count() > $limit ? end($refs) : null);
    }

    public function load(DataScope $scope, RecordRefs $records): SourceRecords
    {
        $this->verify($scope, self::entity(), DB::connection());
        $ids = array_map(static fn (RecordRef $record): string => $record->id, $records->records);
        $rows = DB::table('scenario_records')->whereIn('id', $ids)->get();
        $result = $rows->map(static function (object $row): SourceRecord {
            $values = ['marker' => $row->marker, 'tenant_a_label' => $row->tenant_a_label, 'tenant_b_label' => $row->tenant_b_label];

            return new SourceRecord(new RecordRef(self::ENTITY, $row->id), hash('sha256', json_encode($values, JSON_THROW_ON_ERROR)), new ProjectedValues($values));
        })->all();

        return new SourceRecords($result);
    }

    public function rows(IndexTarget $target, ConnectionInterface $connection): PostgresCorpusRows
    {
        $this->verify($target->scope, $target->entity, $connection);

        return new PostgresCorpusRows($connection->table('scenario_records'), 'id', ['marker' => 'marker']);
    }

    private function verify(DataScope $scope, EntityHandle $entity, ConnectionInterface $connection): void
    {
        if ($entity->value !== self::ENTITY) {
            throw new RuntimeException('The queue scenario has no source for that search entity.');
        }
        app(CurrentWorkScope::class)->assert($scope, $connection);
    }
}
