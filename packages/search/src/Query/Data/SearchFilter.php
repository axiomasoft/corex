<?php

declare(strict_types=1);

namespace CoreX\Search\Query\Data;

use CoreX\Data\Data\FieldSelection;
use InvalidArgumentException;
use Packman\Attributes\Api;

#[Api(since: '2.0.0')]
final readonly class SearchFilter
{
    /** @param list<string|int|bool> $values */
    public function __construct(public string $field, public array $values)
    {
        new FieldSelection([$field]);
        self::validate($values);
    }

    /** @param array<array-key,mixed> $values */
    private static function validate(array $values): void
    {
        if (! array_is_list($values) || $values === [] || count($values) > 100
            || array_any($values, static fn (mixed $v): bool => ! (is_string($v) && strlen($v) <= 4096) && ! is_int($v) && ! is_bool($v))) {
            throw new InvalidArgumentException('Invalid typed search filter.');
        }
    }
}
