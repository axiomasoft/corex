<?php

declare(strict_types=1);

namespace CoreX\Tenancy\Laravel\Support;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;

#[Spi(kind: SpiKind::Call, since: '1.0')]
final readonly class AccountLifecyclePolicy
{
    public function __construct(
        public int $trialDays,
        public int $graceDays,
        public string $platformDomain,
    ) {}
}
