<?php

declare(strict_types=1);

namespace CoreX\CustomFields\Types\Internal;

use CoreX\CustomFields\Types\Contracts\FieldCodec;
use CoreX\CustomFields\Types\Data\FieldValue;
use CoreX\Data\Data\JsonDocument;
use Packman\Attributes\Internal;

#[Internal]
final class JsonFieldCodec implements FieldCodec
{
    public function encode(FieldValue $value): JsonDocument
    {
        return $value->document;
    }

    public function decode(JsonDocument $document): FieldValue
    {
        return new FieldValue($document);
    }
}
