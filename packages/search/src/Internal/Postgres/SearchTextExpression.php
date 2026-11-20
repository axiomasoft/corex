<?php

declare(strict_types=1);

namespace CoreX\Search\Internal\Postgres;

use Illuminate\Contracts\Database\Query\Expression;
use Illuminate\Database\Grammar;
use InvalidArgumentException;
use Packman\Attributes\Internal;

/** @internal Dynamic identifiers come from the validated schema; user text stays bound. */
#[Internal]
final readonly class SearchTextExpression implements Expression
{
    /** @param non-empty-list<string> $columns */
    public function __construct(private array $columns, private string $configuration)
    {
        if (! in_array($configuration, ['simple', 'english'], true)) {
            throw new InvalidArgumentException('PostgreSQL text search configuration is not allowlisted.');
        }
    }

    public function getValue(Grammar $grammar): string
    {
        $columns = implode(', ', array_map(
            static fn (string $column): string => 'COALESCE('.$grammar->wrap($column).", '')",
            $this->columns,
        ));

        return "to_tsvector('{$this->configuration}', concat_ws(' ', {$columns})) @@ websearch_to_tsquery(?, ?)";
    }
}
