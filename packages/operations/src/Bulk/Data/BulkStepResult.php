<?php

declare(strict_types=1);

namespace CoreX\Operations\Bulk\Data;

use CoreX\Operations\Bulk\Enums\BulkStepStatus;
use InvalidArgumentException;
use Packman\Attributes\Api;

#[Api(since: '2.0.0')]
final readonly class BulkStepResult
{
    /** @param array<string, int> $counters */
    public function __construct(
        public BulkStepStatus $status,
        public ?BulkCheckpoint $nextCheckpoint,
        public array $counters,
        public ?BulkOwnerRef $resultRef = null,
        public ?BulkOwnerRef $failurePageRef = null,
        public ?string $errorCode = null,
    ) {
        if (count($counters) > 32 || ($errorCode !== null && preg_match('/\A[a-zA-Z][a-zA-Z0-9_.-]{0,127}\z/D', $errorCode) !== 1)) {
            throw new InvalidArgumentException('Invalid bulk step outcome.');
        }

        self::validateCounters($counters);
    }

    /** @param array<array-key, mixed> $counters */
    private static function validateCounters(array $counters): void
    {
        foreach ($counters as $key => $value) {
            if (! is_string($key) || preg_match('/\A[a-zA-Z][a-zA-Z0-9_]{0,63}\z/D', $key) !== 1 || ! is_int($value) || $value < 0) {
                throw new InvalidArgumentException('Invalid bulk step counter.');
            }
        }
    }
}
