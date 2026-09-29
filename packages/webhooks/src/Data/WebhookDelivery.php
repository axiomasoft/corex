<?php

declare(strict_types=1);

namespace CoreX\Webhooks\Data;

use DateTimeImmutable;
use InvalidArgumentException;

final readonly class WebhookDelivery
{
    /**
     * @param  array<string, scalar|null>  $scope
     */
    public function __construct(
        public string $id,
        public string $subscriptionId,
        public int $subscriptionRevision,
        public string $eventId,
        public string $topic,
        public int $topicVersion,
        public string $major,
        public string $endpointSnapshot,
        public array $scope,
        public string $body,
        public string $bodyHash,
        public string $status,
        public int $attempt,
        public int $replayCount,
        public ?string $leaseOwner,
        public ?DateTimeImmutable $leaseUntil,
        public DateTimeImmutable $nextAttemptAt,
        public DateTimeImmutable $createdAt,
        public ?DateTimeImmutable $finishedAt,
        public DateTimeImmutable $expiresAt,
        public ?string $lastError,
    ) {
        if ($subscriptionRevision < 0 || $topicVersion < 1 || $attempt < 0 || $replayCount < 0) {
            throw new InvalidArgumentException('Webhook delivery counters are invalid.');
        }

        if (! in_array($status, ['pending', 'sending', 'retry', 'succeeded', 'dead'], true)) {
            throw new InvalidArgumentException('Webhook delivery status is not declared.');
        }

        if (! hash_equals(hash('sha256', $body), $bodyHash)) {
            throw new InvalidArgumentException('Webhook delivery body hash does not match the body.');
        }
    }
}
