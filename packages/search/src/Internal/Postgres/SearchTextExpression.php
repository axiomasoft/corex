<?php

declare(strict_types=1);

namespace CoreX\Search\Internal\Postgres;

use CoreX\Search\Data\SearchSnapshot;
use Illuminate\Contracts\Database\Query\Expression;
use Illuminate\Database\Grammar;
use InvalidArgumentException;

/** @internal Dynamic identifiers come from the validated schema; user text stays bound. */
final readonly class SearchTextExpression implements Expression
{
    public function __construct(private SearchSnapshot $schema, private string $configuration)
    {
        if (! in_array($configuration, ['simple', 'english'], true)) {
            throw new InvalidArgumentException('PostgreSQL text search configuration is not allowlisted.');
        }
    }

    public function getValue(Grammar $grammar): string
    {
        $columns = implode(', ', array_map(
            static fn (string $column): string => 'COALESCE('.$grammar->wrap($column).", '')",
            $this->schema->searchableColumns,
        ));

        return "to_tsvector('{$this->configuration}', concat_ws(' ', {$columns})) @@ websearch_to_tsquery(?, ?)";
    }
}
