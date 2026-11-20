<?php

declare(strict_types=1);

namespace CoreX\Workflow\Execution\Internal;

use Packman\Attributes\Internal;
use RuntimeException;
use Stringable;

/** @internal Validate untyped persistence and transport values at the package boundary. */
#[Internal]
final class Value
{
    public static function field(object $row, string $name): mixed
    {
        $fields = get_object_vars($row);

        if (! array_key_exists($name, $fields)) {
            throw new RuntimeException('Missing stored field: '.$name);
        }

        return $fields[$name];
    }

    public static function text(mixed $value): string
    {
        if (! is_string($value) && ! is_int($value) && ! $value instanceof Stringable) {
            throw new RuntimeException('Expected a text value.');
        }

        return (string) $value;
    }

    public static function integer(mixed $value): int
    {
        $integer = filter_var($value, FILTER_VALIDATE_INT);

        if ($integer === false) {
            throw new RuntimeException('Expected an integer value.');
        }

        return $integer;
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
