<?php

declare(strict_types=1);

namespace CoreX\CustomFields\Schemas\Data;

use CoreX\Data\Data\JsonDocument;
use Packman\Attributes\Api;

#[Api(since: '2.0.0')]
final readonly class FieldDescription
{
    public function __construct(public JsonDocument $body, public string $witness) {}
}
