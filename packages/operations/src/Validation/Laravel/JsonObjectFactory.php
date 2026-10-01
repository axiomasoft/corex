<?php

declare(strict_types=1);

namespace CoreX\Operations\Validation\Laravel;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use CoreX\Operations\Validation\Data\JsonObject;

#[Spi(kind: SpiKind::Call, since: '1.0')]
final class JsonObjectFactory
{
    /**
     * @param  array<array-key, mixed>  $value
     */
    public static function fromArray(array $value): JsonObject
    {
        if ($value === []) {
            return new JsonObject('{}');
        }

        return new JsonObject(JsonCanonical::encode($value));
    }

    /**
     * @param  array<string, mixed>  $schema
     */
    public static function fromSchemaArray(array $schema): JsonObject
    {
        return self::fromArray($schema);
    }
}
