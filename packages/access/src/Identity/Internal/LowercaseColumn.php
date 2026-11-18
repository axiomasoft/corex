<?php

declare(strict_types=1);

namespace CoreX\Access\Identity\Internal;

use Illuminate\Contracts\Database\Query\Expression;
use Illuminate\Database\Grammar;
use Override;
use Packman\Attributes\Internal;

/** Quotes the configured identifier while keeping the SQL function fixed. */
#[Internal]
final readonly class LowercaseColumn implements Expression
{
    public function __construct(private string $column) {}

    #[Override]
    public function getValue(Grammar $grammar): string
    {
        return 'lower('.$grammar->wrap($this->column).')';
    }
}
