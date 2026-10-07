<?php

declare(strict_types=1);

namespace CoreX\SearchMeilisearch\Internal;

use RuntimeException;
use Stringable;

/** @internal Validate untyped persistence and transport values at the package boundary. */
final class Value
{
    public static function text(mixed $value): string
    {
        if (! is_string($value) && ! is_int($value) && ! $value instanceof Stringable) {
            throw new RuntimeException('Expected a text value.');
        }

        return (string) $value;
    }

    /** @return array<string, mixed> */
    public static function map(mixed $value): array
    {
        if (! is_array($value)) {
            throw new RuntimeException('Expected a map.');
        }
        $result = [];
        foreach ($value as $key => $item) {
            if (! is_string($key)) {
                throw new RuntimeException('Expected string map keys.');
            }
            $result[$key] = $item;
        }

        return $result;
    }
}
