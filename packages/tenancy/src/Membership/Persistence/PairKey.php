<?php

declare(strict_types=1);

namespace CoreX\Tenancy\Membership\Persistence;

use CoreX\Tenancy\Membership\Transport\Value;

/** @internal Test-only reference key; it is not a runtime tenant binding. */
final readonly class PairKey
{
    public function __construct(
        public string $accountId,
        public string $identityId,
    ) {
        Value::uuid($accountId);
        Value::uuid($identityId);
    }
}
