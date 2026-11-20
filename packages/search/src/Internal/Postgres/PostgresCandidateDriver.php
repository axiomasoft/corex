<?php

declare(strict_types=1);

namespace CoreX\Search\Internal\Postgres;

use Closure;
use CoreX\Context\Contracts\CurrentWorkScope;
use CoreX\Data\Data\DataScope;
use CoreX\Data\Data\EntityHandle;
use CoreX\Data\Data\RecordRef;
use CoreX\Search\Errors\Exceptions\SearchFeatureUnavailable;
use CoreX\Search\Index\Data\IndexTarget;
use CoreX\Search\Postgres\Contracts\PostgresCorpus;
use CoreX\Search\Postgres\Contracts\PostgresSnapshotAuthority;
use CoreX\Search\Postgres\Data\PostgresCorpusRows;
use CoreX\Search\Projection\Data\FieldWitnesses;
use CoreX\Search\Projection\Data\ProjectedValues;
use CoreX\Search\Projection\Data\ProjectionSelection;
use CoreX\Search\Query\Contracts\ExactCandidateDriver;
use CoreX\Search\Query\Data\Candidate;
use CoreX\Search\Query\Data\CandidatePage;
use CoreX\Search\Query\Data\CandidateRequest;
use CoreX\Search\Query\Data\DriverCapabilities;
use CoreX\Search\Query\Data\SearchPage;
use CoreX\Search\Query\Data\SearchStatistics;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\PostgresConnection;
use Illuminate\Database\Query\Builder;
use InvalidArgumentException;
use JsonException;
use Packman\Attributes\Internal;

#[Internal]
final class PostgresCandidateDriver implements ExactCandidateDriver
{
    private ?DataScope $snapshotScope = null;

    public function __construct(
        private readonly ConnectionInterface $connection,
        private readonly CurrentWorkScope $current,
        private readonly PostgresCorpus $corpus,
        private readonly string $configuration = 'simple',
        private readonly string $cluster = 'primary',
        private readonly ?PostgresSnapshotAuthority $authority = null,
    ) {
        if (! in_array($configuration, ['simple', 'english'], true)) {
            throw new InvalidArgumentException('PostgreSQL text search configuration is not allowlisted.');
        }
    }

    public function capabilities(): DriverCapabilities
    {
        return new DriverCapabilities(text: true, filter: true, sort: true, scopedCorpus: true);
    }

    public function target(DataScope $scope, EntityHandle $entity, string $mappingDigest): IndexTarget
    {
        $this->assertConnection($scope);

        return new IndexTarget($scope, $entity, 'postgres', $this->cluster, 1,
            IndexTarget::uidFor($scope, $entity, 'postgres', $this->cluster, 1), $mappingDigest);
    }

    public function candidates(CandidateRequest $request): CandidatePage
    {
        if ($request->limit < 1 || $request->limit > 1000) {
            throw SearchFeatureUnavailable::unsupported();
        }
        [$rows, $query] = $this->filtered($request);
        $ordering = [];

        foreach ($request->sort as $sort) {
            $column = $rows->sortColumns[$sort->field] ?? throw SearchFeatureUnavailable::unsupported();
            $ordering[] = [$column, $sort->descending];

            if ($column === $rows->idColumn) {
                break;
            }
        }

        if ($ordering === [] || $ordering[array_key_last($ordering)][0] !== $rows->idColumn) {
            $ordering[] = [$rows->idColumn, false];
        }
        $query->select($rows->idColumn.' as corex_record_id');
        $searchFields = $request->text === null ? [] : $request->searchFields;

        foreach ($searchFields as $i => $field) {
            $query->addSelect($rows->searchColumns[$field].' as corex_text_'.$i);
        }

        foreach ($ordering as $i => [$column, $descending]) {
            $query->addSelect($column.' as corex_sort_'.$i);
            $query->orderBy(new NullRank($column))->orderBy($column, $descending ? 'desc' : 'asc');
        }

        if ($request->position !== null) {
            $this->after($query, $ordering, $this->position($request->position, count($ordering)));
        }
        $results = $query->limit($request->limit)->get();
        $candidates = [];

        foreach ($results as $row) {
            $id = $row->corex_record_id;

            if (! is_string($id) && ! is_int($id)) {
                throw SearchFeatureUnavailable::unsupported();
            }
            $record = new RecordRef(
                entity: $request->generation->entity->value,
                id: (string) $id,
            );
            $text = [];
            foreach ($searchFields as $i => $field) {
                $value = $row->{'corex_text_'.$i};

                if ($value !== null && ! is_string($value)) {
                    throw SearchFeatureUnavailable::unsupported();
                }
                $text[$field] = $value;
            }
            $witness = $searchFields === [] ? null : FieldWitnesses::capture(new ProjectedValues($text), new ProjectionSelection($searchFields));
            $candidates[] = new Candidate($request->generation, $record, $request->generation->documentId($record), $witness);
        }
        $last = $results->last();
        $next = null;

        if ($results->count() === $request->limit && $last !== null) {
            $keys = [];
            foreach (array_keys($ordering) as $i) {
                $keys[] = $last->{'corex_sort_'.$i};
            }
            $next = json_encode($keys, JSON_THROW_ON_ERROR);
        }

        return new CandidatePage($candidates, $next);
    }

    public function inSnapshot(DataScope $scope, Closure $query): SearchPage
    {
        $this->assertConnection($scope);

        if ($this->authority === null || $this->connection->transactionLevel() !== 0) {
            return $query(false);
        }

        return $this->connection->transaction(function () use ($scope, $query): SearchPage {
            $this->connection->statement("SET LOCAL transaction_isolation TO 'repeatable read'");
            $this->connection->statement('SET LOCAL transaction_read_only TO on');
            $this->assertConnection($scope);

            if (! $this->authority->covers($scope, $this->connection)) {
                return $query(false);
            }
            $this->snapshotScope = $scope;

            try {
                return $query(true);
            } finally {
                $this->snapshotScope = null;
            }
        });
    }

    public function statistics(CandidateRequest $request, bool $count, array $facets): SearchStatistics
    {
        if ($this->snapshotScope !== $request->scope || $this->connection->transactionLevel() === 0) {
            throw SearchFeatureUnavailable::unsupported();
        }
        [$rows, $query] = $this->filtered($request);
        $columns = [];

        foreach ($facets as $field) {
            $columns[$field] = $rows->aggregateColumns[$field] ?? throw SearchFeatureUnavailable::unsupported();
        }
        $total = $count ? (clone $query)->count() : null;
        $buckets = [];

        foreach ($columns as $field => $column) {
            $grouped = (clone $query)->select($column.' as corex_facet')->selectRaw('count(*) as corex_count')
                ->groupBy($column)->limit(1001)->get();

            if ($grouped->count() > 1000) {
                return new SearchStatistics($total, null);
            }
            $buckets[$field] = [];
            foreach ($grouped as $row) {
                $key = json_encode($row->corex_facet, JSON_THROW_ON_ERROR);
                $size = $row->corex_count;

                if (! is_int($size) && (! is_string($size) || preg_match('/\A[0-9]+\z/', $size) !== 1)) {
                    throw SearchFeatureUnavailable::unsupported();
                }
                $buckets[$field][$key] = (int) $size;
            }
            ksort($buckets[$field]);
        }

        return new SearchStatistics($total, $buckets);
    }

    /** @return array{PostgresCorpusRows, Builder} */
    private function filtered(CandidateRequest $request): array
    {
        $target = $request->generation;
        $expected = $this->target($request->scope, $target->entity, $target->mappingDigest);

        if (get_object_vars($target->scope) !== get_object_vars($expected->scope)
            || $target->backend !== $expected->backend || $target->cluster !== $expected->cluster
            || $target->generation !== $expected->generation || $target->uid !== $expected->uid) {
            throw SearchFeatureUnavailable::unsupported();
        }
        $rows = $this->corpus->rows($expected, $this->connection);

        if ($rows->query->getConnection() !== $this->connection || $rows->query->limit !== null
            || $rows->query->offset !== null || $rows->query->groups !== null || $rows->query->unions !== null) {
            throw SearchFeatureUnavailable::unsupported();
        }
        $query = (clone $rows->query)->useWritePdo()->reorder();

        if ($request->recordIds !== null) {
            $query->whereIn($rows->idColumn, $request->recordIds);
        }
        foreach ($request->filters as $filter) {
            $column = $rows->filterColumns[$filter->field] ?? throw SearchFeatureUnavailable::unsupported();
            $query->whereIn($column, $filter->values);
        }

        if ($request->text !== null) {
            $columns = [];
            foreach ($request->searchFields as $field) {
                $columns[] = $rows->searchColumns[$field] ?? throw SearchFeatureUnavailable::unsupported();
            }

            if ($columns === []) {
                throw SearchFeatureUnavailable::unsupported();
            }
            $query->whereRaw(new SearchTextExpression($columns, $this->configuration), [$this->configuration, $request->text]);
        }

        return [$rows, $query];
    }

    private function assertConnection(DataScope $scope): void
    {
        if (! $this->connection instanceof PostgresConnection) {
            throw SearchFeatureUnavailable::unsupported();
        }
        $this->current->assert($scope, $this->connection);
    }

    /** @return list<bool|int|string|null> */
    private function position(string $position, int $length): array
    {
        try {
            $keys = json_decode($position, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw SearchFeatureUnavailable::unsupported();
        }

        if (! is_array($keys) || ! array_is_list($keys) || count($keys) !== $length
            || (! is_string($keys[$length - 1]) && ! is_int($keys[$length - 1]))) {
            throw SearchFeatureUnavailable::unsupported();
        }
        foreach ($keys as $value) {
            if ($value !== null && ! is_bool($value) && ! is_int($value) && ! is_string($value)) {
                throw SearchFeatureUnavailable::unsupported();
            }
        }

        return $keys;
    }

    /** @param list<array{string, bool}> $ordering
     * @param  list<bool|int|string|null>  $keys
     */
    private function after(Builder $query, array $ordering, array $keys): void
    {
        $query->where(function (Builder $keyset) use ($ordering, $keys): void {
            foreach ($ordering as $i => [$column, $descending]) {
                $value = $keys[$i];

                if ($value === null) {
                    continue;
                }
                $keyset->orWhere(function (Builder $suffix) use ($ordering, $keys, $i, $column, $descending, $value): void {
                    for ($prefix = 0; $prefix < $i; $prefix++) {
                        $suffix->where($ordering[$prefix][0], '=', $keys[$prefix]);
                    }
                    $suffix->where(static function (Builder $after) use ($column, $descending, $value): void {
                        $after->where($column, $descending ? '<' : '>', $value)->orWhereNull($column);
                    });
                });
            }
        });
    }
}
