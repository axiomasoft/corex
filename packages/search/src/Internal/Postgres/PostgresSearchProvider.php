<?php

declare(strict_types=1);

namespace CoreX\Search\Internal\Postgres;

use CoreX\Data\Data\RecordRef;
use CoreX\Data\DataScope;
use CoreX\Search\Contracts\SearchProvider;
use CoreX\Search\Contracts\SearchSchema;
use CoreX\Search\Data\Candidate;
use CoreX\Search\Data\CandidatePage;
use CoreX\Search\Data\SearchCapabilities;
use CoreX\Search\Data\SearchFilter;
use CoreX\Search\Data\SearchQuery;
use CoreX\Search\Data\SearchSnapshot;
use CoreX\Search\Internal\Query\SearchCursorCodec;
use Illuminate\Database\ConnectionInterface;
use InvalidArgumentException;

final class PostgresSearchProvider implements SearchProvider
{
    /** @var list<string> */
    private const AllowedConfigurations = ['simple', 'english'];

    public function __construct(
        private ConnectionInterface $connection,
        private SearchSchema $schemas,
        private SearchCursorCodec $cursors,
        private string $configuration = 'simple',
    ) {
        if (! in_array($configuration, self::AllowedConfigurations, true)) {
            throw new InvalidArgumentException('PostgreSQL text search configuration is not allowlisted.');
        }
    }

    public function capabilities(): SearchCapabilities
    {
        return new SearchCapabilities(fullText: true);
    }

    public function query(DataScope $scope, SearchQuery $query): CandidatePage
    {
        $schema = $this->schemas->describe(scope: $scope, entity: $query->entity);
        $this->assertSchema(query: $query, schema: $schema);

        $builder = $this->connection->table($schema->table)
            ->select($schema->idColumn)
            ->orderBy($schema->idColumn)
            ->limit(min($query->scanBudget, $query->limit + 1));

        if ($query->afterId !== null) {
            $builder->where($schema->idColumn, '>', $this->cursors->decode(
                scope: $scope,
                query: $query,
                schema: $schema,
                cursor: $query->afterId,
            ));
        }

        foreach ($query->filters as $filter) {
            $this->applyFilter(builder: $builder, schema: $schema, filter: $filter);
        }

        $columns = implode(', ', array_map(static fn (string $column): string => 'COALESCE('.$column.", '')", $schema->searchableColumns));
        $builder->whereRaw("to_tsvector('{$this->configuration}', concat_ws(' ', {$columns})) @@ websearch_to_tsquery(?, ?)", [$this->configuration, $query->text]);

        $rows = $builder->get();
        $truncated = $rows->count() > $query->limit;
        $visible = $rows->take($query->limit);
        $candidates = $visible->map(static fn (object $row): Candidate => new Candidate(new RecordRef(entity: $query->entity, id: (string) $row->{$schema->idColumn})))->all();
        $next = $truncated ? $this->cursors->encode(
            scope: $scope,
            query: $query,
            schema: $schema,
            id: (string) $visible->last()->{$schema->idColumn},
        ) : null;

        return new CandidatePage(candidates: $candidates, nextAfterId: $next, truncated: $truncated);
    }

    private function assertSchema(SearchQuery $query, SearchSnapshot $schema): void
    {
        if ($schema->schemaHash !== $query->schemaHash || $schema->entity !== $query->entity) {
            throw new InvalidArgumentException('Search schema is stale for this query.');
        }
    }

    private function applyFilter(object $builder, SearchSnapshot $schema, SearchFilter $filter): void
    {
        $column = $schema->filterColumns[$filter->field] ?? null;

        if ($column === null) {
            throw new InvalidArgumentException('Search filter is not approved by the schema.');
        }

        $builder->whereIn($column, $filter->values);
    }
}
