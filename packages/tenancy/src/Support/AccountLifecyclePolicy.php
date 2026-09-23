<?php

declare(strict_types=1);

namespace CoreX\Tenancy\Support;

final readonly class AccountLifecyclePolicy
{
    public function __construct(
        public int $trialDays,
        public int $graceDays,
        public string $platformDomain,
    ) {}
}
