<?php

declare(strict_types=1);

namespace CoreX\CustomFields\Types\Data;

use InvalidArgumentException;
use Packman\Attributes\Internal;

#[Internal]
final class FieldList
{
    /**
     * @param  array<array-key,mixed>  $values
     * @param  list<class-string>  $classes
     */
    public static function check(array $values, array $classes, int $min = 0, int $max = 256): void
    {
        if (! array_is_list($values) || count($values) < $min || count($values) > $max) {
            throw new InvalidArgumentException('Invalid field list.');
        }
        foreach ($values as $value) {
            if (! array_any($classes, static fn (string $class): bool => $value instanceof $class)) {
                throw new InvalidArgumentException('Invalid field list item.');
            }
        }
    }
}
