<?php

declare(strict_types=1);

namespace CoreX\Tenancy\Laravel\Support;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;

#[Spi(kind: SpiKind::Call, since: '1.0')]
final readonly class ImpersonationPolicy
{
    public function __construct(
        public bool $enabled,
        public bool $requireApprovedBy,
    ) {}
}
