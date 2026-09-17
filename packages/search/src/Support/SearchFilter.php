<?php

declare(strict_types=1);

namespace CoreX\Search\Support;

use InvalidArgumentException;

final readonly class SearchFilter
{
    /** @param list<mixed> $values */
    public function __construct(
        public string $field,
        public array $values,
    ) {
        if ($field === '' || $values === [] || count($values) > 100 || count(array_filter($values, static fn (mixed $value): bool => ! is_string($value) || $value === '')) > 0) {
            throw new InvalidArgumentException('Search filters must contain 1 to 100 non-empty string values.');
        }
    }
}
