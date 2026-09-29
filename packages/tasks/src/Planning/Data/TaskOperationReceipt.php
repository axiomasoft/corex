<?php

declare(strict_types=1);

namespace CoreX\Tasks\Planning\Data;

/** Immutable view of a durable operation receipt; no table access escapes the owner. */
final readonly class TaskOperationReceipt
{
    /** @param array<string, mixed> $resultRef */
    public function __construct(
        public string $requestKey,
        public string $payloadHash,
        public array $resultRef,
    ) {}
}
