<?php

declare(strict_types=1);

namespace CoreX\Operations\Validation\Laravel;

use CoreX\Operations\Validation\Data\JsonValue;
use Packman\Attributes\Spi;
use Packman\Attributes\SpiKind;

#[Spi(kind: SpiKind::Call, since: '1.0')]
final class JsonValueFactory
{
    public static function fromDecoded(mixed $value): JsonValue
    {
        return new JsonValue(JsonCanonical::encode($value));
    }
}
