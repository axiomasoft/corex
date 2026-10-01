<?php

declare(strict_types=1);

namespace CoreX\Tenancy\Membership\Data;

use CoreX\Attributes\Api;
use CoreX\Tenancy\Membership\Internal\Transport\Value;

#[Api(since: '1.0')]
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
