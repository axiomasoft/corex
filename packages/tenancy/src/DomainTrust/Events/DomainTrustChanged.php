<?php

declare(strict_types=1);

namespace CoreX\Tenancy\DomainTrust\Events;

use CoreX\Tenancy\DomainTrust\Data\DomainTrustTransition;

final class DomainTrustChanged
{
    public function __construct(public readonly DomainTrustTransition $transition) {}
}
