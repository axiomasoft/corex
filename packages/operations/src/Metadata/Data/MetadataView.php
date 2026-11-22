<?php

declare(strict_types=1);

namespace CoreX\Operations\Metadata\Data;

use CoreX\Data\Data\JsonDocument;
use Packman\Attributes\Api;

#[Api(since: '2.0.0')]
final readonly class MetadataView
{
    public function __construct(public JsonDocument $body, public string $witness) {}
}
