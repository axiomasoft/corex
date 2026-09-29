<?php

declare(strict_types=1);

namespace CoreX\Operations\Idempotency\Contracts;

use CoreX\Operations\Idempotency\Data\IdempotencyAttempt;
use CoreX\Operations\Idempotency\Data\IdempotencyScope;

/** Durable tenant-local admission and semantic-outcome store for mutations. */
interface IdempotencyStore
{
    public function begin(
        IdempotencyScope $scope,
        string $key,
        string $requestFingerprint,
        string $contractHash,
    ): IdempotencyAttempt;

    /** Claim an expired lease only after the caller has established rollback/reconciliation safety. */
    public function takeover(IdempotencyAttempt $attempt): IdempotencyAttempt;

    public function complete(IdempotencyAttempt $attempt, string $outcomeJson): void;

    public function fail(IdempotencyAttempt $attempt, string $outcomeJson): void;

    /** Delete expired terminal rows from the active tenant database. */
    public function prune(): int;
}
