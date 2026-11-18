<?php

declare(strict_types=1);

namespace CoreX\Tasks\Planning\Internal;

use Packman\Attributes\Internal;
use RuntimeException;
use Stringable;

/** @internal Validate untyped persistence and transport values at the package boundary. */
#[Internal]
final class Value
{
    public static function text(mixed $value): string
    {
        if (! is_string($value) && ! is_int($value) && ! $value instanceof Stringable) {
            throw new RuntimeException('Expected a text value.');
        }

        return (string) $value;
    }
}
