<?php

declare(strict_types=1);

namespace CoreX\Tools\Data;

use CoreX\Attributes\Api;
use CoreX\Data\Data\JsonDocument;
use CoreX\Operations\Validation\Data\JsonObject;

/** Typed tool outcome without leaking infrastructure details. */
#[Api(since: '1.0')]
final readonly class ToolResult
{
    public const OUTCOME_NOT_EXECUTED = 'not-executed';

    public const OUTCOME_COMMITTED = 'committed';

    public const OUTCOME_UNKNOWN = 'unknown';

    /** Immutable JSON value. */
    public function __construct(public string $outcome, public ?JsonObject $structuredData, public ?string $errorCode, public ?string $operationKey, public bool $replayed, public JsonDocument $metadata = new JsonDocument('[]')) {}
}
