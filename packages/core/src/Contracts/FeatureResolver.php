<?php

declare(strict_types=1);

namespace CoreX\Contracts;

/**
 * Feature-flag resolver. Thin in the kernel (config-driven); a full Pennant
 * implementation with tenant scope and tier gating lands in v2.
 */
interface FeatureResolver
{
    public function active(string $feature, ?Tenant $for = null): bool;
}
