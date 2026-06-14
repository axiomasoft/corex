<?php

declare(strict_types=1);

namespace CoreX\Features;

use CoreX\Contracts\FeatureResolver;
use CoreX\Contracts\Tenant;

/**
 * Minimal config-driven feature flags: corex.features.<feature> => bool.
 * A full Pennant-backed resolver with tenant scope and tier gating is v2.
 */
final class ConfigFeatureResolver implements FeatureResolver
{
    public function active(string $feature, ?Tenant $for = null): bool
    {
        return (bool) config("corex.features.{$feature}", false);
    }
}
