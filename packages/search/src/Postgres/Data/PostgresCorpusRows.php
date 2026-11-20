<?php

declare(strict_types=1);

namespace CoreX\Search\Postgres\Data;

use Illuminate\Database\Query\Builder;
use InvalidArgumentException;
use Packman\Attributes\Spi;
use Packman\Attributes\SpiKind;

/** One row per record in the selected scope, with a unique and non-null record identifier. */
#[Spi(kind: SpiKind::Call, since: '2.0.0')]
final readonly class PostgresCorpusRows
{
    /**
     * @param  array<string, string>  $searchColumns  Projection field ID to text column.
     * @param  array<string, string>  $filterColumns  Projection field ID to equality column.
     * @param  array<string, string>  $sortColumns  Projection field ID to stable ordering column.
     * @param  array<string, string>  $aggregateColumns  Projection field ID to facet column.
     */
    public function __construct(
        public Builder $query,
        public string $idColumn,
        public array $searchColumns = [],
        public array $filterColumns = [],
        public array $sortColumns = [],
        public array $aggregateColumns = [],
    ) {
        foreach ([$idColumn, ...array_values($searchColumns), ...array_values($filterColumns), ...array_values($sortColumns), ...array_values($aggregateColumns)] as $column) {
            self::assertIdentifier($column);
        }
    }

    private static function assertIdentifier(mixed $column): void
    {
        if (! is_string($column) || preg_match('/\A[a-z][a-z0-9_]{0,62}(?:\.[a-z][a-z0-9_]{0,62})?\z/', $column) !== 1) {
            throw new InvalidArgumentException('A PostgreSQL Search column must be a trusted identifier.');
        }
    }
}
