<?php

declare(strict_types=1);

namespace CoreX\Operations\Bulk\Data;

use Packman\Attributes\Api;

/** Immutable durable-bulk activation policy read only by the package registrar. */
#[Api(since: '1.0')]
final readonly class BulkOptions
{
    public function __construct(
        public bool $enabled,
        public string $worker,
        public int $leaseSeconds,
        public int $retrySeconds,
        public int $resultRetentionSeconds,
        public int $metadataRetentionSeconds,
        public int $idempotencyRetentionSeconds,
        public int $rowLimit = 500,
        public int $timeLimitSeconds = 20,
        public int $maxRecoveryAttempts = 5,
    ) {}

}
