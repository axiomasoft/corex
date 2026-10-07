<?php

declare(strict_types=1);

namespace CoreX\Intl\Territory\Internal;

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

    public static function nullableText(mixed $value): ?string
    {
        return $value === null ? null : self::text($value);
    }

    public static function integer(mixed $value): int
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_string($value) && preg_match('/^-?(?:0|[1-9][0-9]*)$/D', $value) === 1) {
            $integer = (int) $value;

            if ((string) $integer === $value || $value === '-0') {
                return $integer;
            }
        }

        throw new RuntimeException('Expected a database integer within the native range.');
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

    /** @return list<string> */
    public static function strings(mixed $value): array
    {
        if (! is_array($value) || ! array_is_list($value)) {
            throw new RuntimeException('Expected a string list.');
        }
        $result = [];
        foreach ($value as $item) {
            $result[] = self::text($item);
        }

        return $result;
    }

    /** @return array<array-key, mixed> */
    public static function array(mixed $value): array
    {
        if (! is_array($value)) {
            throw new RuntimeException('Expected an array.');
        }

        return $value;
    }

    /** @return list<array<string, mixed>> */
    public static function maps(mixed $value): array
    {
        $result = [];
        foreach (self::array($value) as $item) {
            $result[] = self::map($item);
        }

        return $result;
    }
}
