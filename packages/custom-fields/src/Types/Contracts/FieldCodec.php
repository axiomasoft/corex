<?php

declare(strict_types=1);

namespace CoreX\CustomFields\Types\Contracts;

use CoreX\CustomFields\Types\Data\FieldValue;
use CoreX\Data\Data\JsonDocument;
use Packman\Attributes\Spi;
use Packman\Attributes\SpiKind;

#[Spi(kind: SpiKind::Implement, since: '2.0.0')]
interface FieldCodec
{
    public function encode(FieldValue $value): JsonDocument;

    public function decode(JsonDocument $document): FieldValue;
}
