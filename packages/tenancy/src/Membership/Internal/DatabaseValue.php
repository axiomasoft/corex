<?php

declare(strict_types=1);

namespace CoreX\Tenancy\Membership\Internal;

use Packman\Attributes\Internal;
use RuntimeException;
use stdClass;

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

    public static function decimal(mixed $value): string
    {
        if ((! is_string($value) && ! is_int($value)) || preg_match('/^(?:0|[1-9][0-9]*)$/D', (string) $value) !== 1) {
            throw new RuntimeException('Expected an unsigned database decimal value.');
        }

        return (string) $value;
    }

    public static function row(mixed $value): stdClass
    {
        if (! $value instanceof stdClass) {
            throw new RuntimeException('Expected a database result row.');
        }

        return $value;
    }
}
