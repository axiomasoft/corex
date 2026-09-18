<?php

declare(strict_types=1);

namespace CoreX\Api\Data;

/** Typed tool outcome without leaking infrastructure details. */
final readonly class ToolResult
{
    public const OUTCOME_NOT_EXECUTED = 'not-executed';

    public const OUTCOME_COMMITTED = 'committed';

    public const OUTCOME_UNKNOWN = 'unknown';

    public function __construct(
        public string $outcome,
        public ?JsonObject $structuredData,
        public ?string $errorCode,
        public ?string $operationKey,
        public bool $replayed,
    ) {}
}
