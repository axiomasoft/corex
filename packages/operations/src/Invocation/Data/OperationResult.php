<?php

declare(strict_types=1);

namespace CoreX\Operations\Invocation\Data;

use CoreX\Attributes\Api;
use CoreX\Data\Data\JsonDocument;

#[Api(since: '1.0')]
final readonly class OperationResult
{
    public function __construct(public JsonDocument $data, public bool $replayed = false, public JsonDocument $metadata = new JsonDocument('[]')) {}
}
