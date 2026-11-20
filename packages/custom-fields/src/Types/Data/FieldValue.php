<?php

declare(strict_types=1);

namespace CoreX\CustomFields\Types\Data;

use CoreX\Data\Data\JsonDocument;
use Packman\Attributes\Api;

/** @phpstan-import-type Json8 from FieldInput */
#[Api(since: '2.0.0')]
final readonly class FieldValue
{
    public function __construct(public JsonDocument $document) {}

    /** @param Json8 $value */
    public static function from(null|bool|int|float|string|array $value): self
    {
        return new self(new JsonDocument(FieldJson::encode($value)));
    }
}
