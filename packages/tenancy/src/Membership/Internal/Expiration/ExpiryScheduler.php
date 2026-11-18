<?php

declare(strict_types=1);

namespace CoreX\Tenancy\Membership\Internal\Expiration;

use CoreX\Tenancy\Membership\Data\PairKey;
use CoreX\Tenancy\Membership\Data\PairState;
use CoreX\Tenancy\Membership\Internal\Persistence\PairStore;
use Packman\Attributes\Internal;

/** @internal Fixture-only priority expiry; no worker or kill mechanism is registered. */
#[Internal]
final readonly class ExpiryScheduler
{
    public function __construct(private PairStore $pairs) {}

    public function expire(PairKey $key, string $barrierId): PairState
    {
        return $this->pairs->invalidate(key: $key, barrierId: $barrierId);
    }
}
