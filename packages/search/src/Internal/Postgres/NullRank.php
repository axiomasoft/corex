<?php

declare(strict_types=1);

namespace CoreX\Search\Internal\Postgres;

use Illuminate\Contracts\Database\Query\Expression;
use Illuminate\Database\Grammar;
use Packman\Attributes\Internal;

#[Internal]
final readonly class NullRank implements Expression
{
    public function __construct(private string $column) {}

    public function getValue(Grammar $grammar): string
    {
        return $grammar->wrap($this->column).' IS NULL';
    }
}
