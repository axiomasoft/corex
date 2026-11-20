<?php

declare(strict_types=1);

namespace CoreX\CustomFields\Types\Data;

use CoreX\Data\Data\JsonDocument;
use Packman\Attributes\Api;

/**
 * @phpstan-type ScalarJson null|bool|int|float|string
 * @phpstan-type Json1 ScalarJson|array<array-key,ScalarJson>
 * @phpstan-type Json2 ScalarJson|array<array-key,Json1>
 * @phpstan-type Json3 ScalarJson|array<array-key,Json2>
 * @phpstan-type Json4 ScalarJson|array<array-key,Json3>
 * @phpstan-type Json5 ScalarJson|array<array-key,Json4>
 * @phpstan-type Json6 ScalarJson|array<array-key,Json5>
 * @phpstan-type Json7 ScalarJson|array<array-key,Json6>
 * @phpstan-type Json8 ScalarJson|array<array-key,Json7>
 */
#[Api(since: '2.0.0')]
final readonly class FieldInput
{
    public function __construct(public JsonDocument $document) {}

    /** @param Json8 $value */
    public static function from(null|bool|int|float|string|array $value): self
    {
        return new self(new JsonDocument(FieldJson::encode($value)));
    }
}
