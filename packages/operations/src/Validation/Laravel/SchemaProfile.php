<?php

declare(strict_types=1);

namespace CoreX\Operations\Validation\Laravel;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;

/** Frozen local policy for tool-schema/1 bounded validation. */
#[Spi(kind: SpiKind::Call, since: '1.0')]
final class SchemaProfile
{
    public const string TOOL_SCHEMA_V1 = 'tool-schema/1';

    public const int MAX_SCHEMA_BYTES = 131_072;

    public const int MAX_RESOURCES = 128;

    public const int MAX_DEPTH = 32;

    public const int MAX_NODES = 4096;

    public const int MAX_PAYLOAD_BYTES = 131_072;

    public const int MAX_PAYLOAD_MEMBERS = 4096;

    public const int MAX_REGEX_BYTES = 256;

    public const int MAX_ERRORS = 16;

    public const int WORKER_DEADLINE_MS = 100;

    public const WORKER_MEMORY_BYTES = 64 * 1024 * 1024;

    /** @var list<string> */
    public const array REJECTED_KEYWORDS = [
        '$dynamicRef',
        '$dynamicAnchor',
    ];
}
