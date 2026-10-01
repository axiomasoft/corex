<?php

declare(strict_types=1);

namespace CoreX\Operations\Idempotency\Data;

use CoreX\Attributes\Api;
use DateTimeImmutable;
use InvalidArgumentException;

/** A fenced store decision. It never contains the caller's raw idempotency key. */
#[Api(since: '1.0')]
final readonly class IdempotencyAttempt
{
    public const string Owner = 'owner';

    public const string Replay = 'replay';

    public const string Conflict = 'conflict';

    public const string InProgress = 'in_progress';

    public const string ContractMismatch = 'contract_mismatch';

    public function __construct(
        public string $disposition,
        public IdempotencyScope $scope,
        public string $scopeHash,
        public string $keyHash,
        public string $requestFingerprint,
        public string $contractHash,
        public string $state,
        public int $attempt,
        public ?string $leaseToken,
        public ?DateTimeImmutable $leaseUntil,
        public ?string $outcomeJson,
    ) {
        if (! in_array($disposition, [
            self::Owner,
            self::Replay,
            self::Conflict,
            self::InProgress,
            self::ContractMismatch,
        ], true)) {
            throw new InvalidArgumentException('Unknown idempotency attempt disposition.');
        }

        if ($attempt < 1) {
            throw new InvalidArgumentException('Idempotency attempt number must be positive.');
        }

        if ($disposition === self::Owner && ($state !== 'started' || $leaseToken === null || $leaseUntil === null)) {
            throw new InvalidArgumentException('An owning idempotency attempt requires an active lease.');
        }

        if ($disposition === self::Replay && (! in_array($state, ['completed', 'failed'], true) || $outcomeJson === null)) {
            throw new InvalidArgumentException('A replay attempt requires a terminal outcome.');
        }
    }

    public function ownsLease(): bool
    {
        return $this->disposition === self::Owner;
    }
}
