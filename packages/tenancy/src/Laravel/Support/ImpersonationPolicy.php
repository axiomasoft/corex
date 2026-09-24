<?php

declare(strict_types=1);

namespace CoreX\Tenancy\Support;

/** @internal Package composition type. */
final readonly class ImpersonationPolicy
{
    public function __construct(
        public bool $enabled,
        public bool $requireApprovedBy,
    ) {}
}
