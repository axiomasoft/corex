<?php

declare(strict_types=1);

namespace CoreX\Operations\Bulk\Data;

use InvalidArgumentException;
use Packman\Attributes\Api;

#[Api(since: '2.0.0')]
final readonly class BulkStep
{
    public function __construct(
        public string $token,
        public ?BulkCheckpoint $checkpoint,
        public int $rowLimit,
        public int $timeLimitSeconds,
        public bool $cancelRequested,
    ) {
        if (preg_match('/\A[a-f0-9]{64}\z/D', $token) !== 1 || $rowLimit < 1 || $rowLimit > 500 || $timeLimitSeconds < 1 || $timeLimitSeconds > 30) {
            throw new InvalidArgumentException('Invalid bulk step identity or limit.');
        }
    }
}
