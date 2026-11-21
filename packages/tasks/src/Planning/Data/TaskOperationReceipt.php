<?php

declare(strict_types=1);

namespace CoreX\Tasks\Planning\Data;

use CoreX\Data\Data\JsonDocument;
use InvalidArgumentException;
use Packman\Attributes\Api;

/** Immutable operation outcome with the digest and fence of its original admission. */
#[Api(since: '1.0')]
final readonly class TaskOperationReceipt
{
    public function __construct(
        public string $payloadHash,
        public TaskOperationFence $originalFence,
        public JsonDocument $resultRef,
    ) {
        if (preg_match('/\A[a-f0-9]{64}\z/D', $payloadHash) !== 1) {
            throw new InvalidArgumentException('Invalid task operation result digest.');
        }
    }
}
