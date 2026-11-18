<?php

declare(strict_types=1);

namespace CoreX\Tools\Data;

use CoreX\Data\Data\JsonDocument;
use CoreX\Operations\Validation\Data\JsonObject;
use Packman\Attributes\Api;

/** Typed tool outcome without leaking infrastructure details. */
#[Api(since: '1.0')]
final readonly class ToolResult
{
    /** Immutable JSON value. */
    public function __construct(public ToolOutcome $outcome, public ?JsonObject $structuredData, public ?string $errorCode, public ?string $operationKey, public bool $replayed, public JsonDocument $metadata = new JsonDocument('[]')) {}
}
