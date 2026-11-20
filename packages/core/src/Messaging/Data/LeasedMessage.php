<?php

declare(strict_types=1);

namespace CoreX\Messaging\Data;

use DateTimeImmutable;
use Packman\Attributes\Api;

#[Api(since: '1.0')]
final readonly class LeasedMessage
{
    public function __construct(
        public MessageEnvelope $envelope,
        public string $leaseOwner,
        public int $attempt,
        public DateTimeImmutable $leaseUntil,
        public string $rowId,
        public string $claimToken,
    ) {}
}
