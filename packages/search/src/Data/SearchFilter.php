<?php

declare(strict_types=1);

namespace CoreX\Search\Data;

use InvalidArgumentException;
use Packman\Attributes\Api;

#[Api(since: '1.0')]
final readonly class SearchFilter
{
    /** @param list<string> $values */
    public function __construct(
        public string $field,
        public array $values,
    ) {
        self::validateInput(field: $field, values: $values);
    }

    /** Runtime validation also rejects values from untyped integration callers.
     * @param  array<array-key, mixed>  $values
     */
    private static function validateInput(
        /** Runtime input. */
        string $field,
        /** Runtime input. */
        array $values,
    ): void {
        if ($field === '' || $values === [] || count($values) > 100 || count(array_filter($values, static fn (mixed $value): bool => ! is_string($value) || $value === '')) > 0) {
            throw new InvalidArgumentException('Search filters must contain 1 to 100 non-empty string values.');
        }
    }
}
