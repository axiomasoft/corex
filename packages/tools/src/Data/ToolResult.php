<?php

declare(strict_types=1);

namespace CoreX\Tools\Data;

use CoreX\Operations\Validation\Data\JsonObject;

/** Typed tool outcome without leaking infrastructure details. */
final readonly class ToolResult
{
    public const OUTCOME_NOT_EXECUTED = 'not-executed';

    public const OUTCOME_COMMITTED = 'committed';

    public const OUTCOME_UNKNOWN = 'unknown';

    /** @param array<string, mixed> $metadata */
    public function __construct(
        public string $outcome,
        public ?JsonObject $structuredData,
        public ?string $errorCode,
        public ?string $operationKey,
        public bool $replayed,
        public array $metadata = [],
    ) {}
}
