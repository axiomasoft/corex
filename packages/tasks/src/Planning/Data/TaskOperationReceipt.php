<?php

declare(strict_types=1);

namespace CoreX\Tasks\Planning\Data;

use CoreX\Attributes\Api;
use CoreX\Data\Data\JsonDocument;

/** Immutable view of a durable operation receipt; no table access escapes the owner. */
#[Api(since: '1.0')]
final readonly class TaskOperationReceipt
{
    /** Closed JSON reference shared by planning and materialization receipts. */
    public function __construct(
        public string $requestKey,
        public string $payloadHash,
        public JsonDocument $resultRef,
    ) {}
}
