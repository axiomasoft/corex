<?php

declare(strict_types=1);

namespace CoreX\Operations\Validation;

/** Frozen local policy for tool-schema/1 bounded validation. */
final class SchemaProfile
{
    public const TOOL_SCHEMA_V1 = 'tool-schema/1';

    public const MAX_SCHEMA_BYTES = 131_072;

    public const MAX_RESOURCES = 128;

    public const MAX_DEPTH = 32;

    public const MAX_NODES = 4096;

    public const MAX_PAYLOAD_BYTES = 131_072;

    public const MAX_PAYLOAD_MEMBERS = 4096;

    public const MAX_REGEX_BYTES = 256;

    public const MAX_ERRORS = 16;

    public const WORKER_DEADLINE_MS = 100;

    public const WORKER_MEMORY_BYTES = 64 * 1024 * 1024;

    /** @var list<string> */
    public const REJECTED_KEYWORDS = [
        '$dynamicRef',
        '$dynamicAnchor',
    ];
}
