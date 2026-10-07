<?php

declare(strict_types=1);

namespace CoreX\Tenancy\Identity\Internal;

use RuntimeException;

/** @internal Decode this module's PDO values without coercing malformed state. */
final class DatabaseValue
{
    public static function text(mixed $value): string
    {
        if (! is_string($value)) {
            throw new RuntimeException('Expected a database text value.');
        }

        return $value;
    }
}
