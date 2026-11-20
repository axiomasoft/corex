<?php

declare(strict_types=1);

namespace CoreX\CustomFields\Types\Contracts;

use CoreX\CustomFields\Schemas\Data\FieldDefinition;
use CoreX\Data\Data\JsonDocument;
use Packman\Attributes\Internal;

#[Internal]
interface FieldConversions
{
    public function convert(JsonDocument $document, FieldDefinition $from, FieldDefinition $to, ?string $converterId, ?int $converterVersion): JsonDocument;
}
