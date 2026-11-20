<?php

declare(strict_types=1);

namespace CoreX\Search\Projection\Data;

use InvalidArgumentException;
use Packman\Attributes\Internal;

#[Internal]
final class ProjectionInput
{
    /** @param array<array-key, mixed> $items
     * @param class-string $class */
    public static function objects(array $items, string $class, int $max = 1000): void
    {
        if (! array_is_list($items) || count($items) > $max || array_any($items, static fn (mixed $item): bool => ! $item instanceof $class)) {
            throw new InvalidArgumentException('Expected a bounded list of typed search values.');
        }
    }

    public static function digest(string $digest): void
    {
        if (preg_match('/\A[a-f0-9]{64}\z/D', $digest) !== 1) {
            throw new InvalidArgumentException('Expected a SHA-256 search revision.');
        }
    }

    /** @param array<array-key,mixed> $values */
    public static function scalarMap(array $values): void
    {
        if (count($values) > 256) {
            throw new InvalidArgumentException('Projection exceeds the supported number of fields.');
        }
        foreach ($values as $id => $value) {
            if (! is_string($id) || preg_match('/\A[A-Za-z0-9][A-Za-z0-9._:-]{0,127}\z/D', $id) !== 1
                || (! is_string($value) && ! is_int($value) && ! is_bool($value) && $value !== null)) {
                throw new InvalidArgumentException('Projection requires stable field IDs and typed scalar values.');
            }
        }
    }
}
