<?php

declare(strict_types=1);

namespace CoreX\Tenancy\Support;

final readonly class ImpersonationPolicy
{
    public function __construct(
        public bool $enabled,
        public bool $requireApprovedBy,
    ) {}
}
