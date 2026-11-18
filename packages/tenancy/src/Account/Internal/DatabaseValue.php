<?php

declare(strict_types=1);

namespace CoreX\Tenancy\Account\Internal;

use Packman\Attributes\Internal;
use RuntimeException;

/** @internal Decode this module's PDO values without coercing malformed state. */
#[Internal]
final class DatabaseValue
{
    public static function text(mixed $value): string
    {
        if (! is_string($value)) {
            throw new RuntimeException('Expected a database text value.');
        }

        return $value;
    }

    public static function integer(mixed $value): int
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_string($value) && preg_match('/^-?(?:0|[1-9][0-9]*)$/D', $value) === 1) {
            $integer = filter_var($value, FILTER_VALIDATE_INT);

            if (is_int($integer)) {
                return $integer;
            }
        }

        throw new RuntimeException('Expected a database integer within the native range.');
    }
}
