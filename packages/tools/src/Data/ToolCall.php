<?php

declare(strict_types=1);

namespace CoreX\Tools\Data;

use CoreX\Operations\Validation\Data\JsonObject;
use Packman\Attributes\Api;

/** Typed headless tool invocation request; correlation and proof are untrusted evidence. */
#[Api(since: '1.0')]
final readonly class ToolCall
{
    public function __construct(
        public string $code,
        public string $toolVersion,
        public string $expectedContractHash,
        public JsonObject $arguments,
        public string $requestId,
        public ?string $operationKey = null,
        public ?string $clientProof = null,
    ) {}
}
