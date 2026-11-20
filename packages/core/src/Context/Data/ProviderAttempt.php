<?php

declare(strict_types=1);

namespace CoreX\Context\Data;

use DateTimeImmutable;
use InvalidArgumentException;
use Packman\Attributes\Api;

#[Api(since: '2.0.0')]
final readonly class ProviderAttempt
{
    public function __construct(
        public ProviderEffectRef $effect,
        public string $ownerRef,
        public string $claimToken,
        public int $attempt,
        public DateTimeImmutable $deadline,
        public string $digest,
        /** Accepted and Confirmed must identify one provider instance for this logical effect. */
        public bool $singleInstanceReference = false,
    ) {
        if ($ownerRef === '' || strlen($ownerRef) > 191 || $claimToken === '' || strlen($claimToken) > 191
            || $attempt < 1 || preg_match('/\A[a-f0-9]{64}\z/D', $digest) !== 1) {
            throw new InvalidArgumentException('Invalid provider attempt.');
        }
    }
}
