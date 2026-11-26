<?php

declare(strict_types=1);

namespace CoreX\Messaging\Data;

use Packman\Attributes\Api;

/**
 * Outbox delivery health. The oldest pending message age measures delivery delay,
 * while queue depth measures outstanding work. Consumers track duplicates when
 * `Inbox::claim()` returns false. The outbox tables cannot supply that count.
 */
#[Api(since: '1.0')]
final readonly class DeliveryHealth
{
    /** @param array<string, int> $oldestPendingSecondsByContract */
    public function __construct(
        public ?int $oldestPendingSeconds,
        public array $oldestPendingSecondsByContract,
        public int $pendingCount,
        public int $publishingCount,
        public int $deadCount,
        public int $maxAttempts,
        public int $quarantinedCount,
    ) {}
}
