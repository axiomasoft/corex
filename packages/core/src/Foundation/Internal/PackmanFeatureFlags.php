<?php

declare(strict_types=1);

namespace CoreX\Foundation\Internal;

use CoreX\Data\Data\JsonDocument;
use CoreX\Features\Contracts\FeatureFlags;
use CoreX\Features\Data\FeatureFlagDefinition as CoreFeatureFlagDefinition;
use Packman\Attributes\Internal;
use Packman\Features\Contracts\FeatureFlagsInterface;
use Packman\Features\Data\FeatureFlagDefinition;

/** Packman declares defaults; CoreX persists account feature state. */
#[Internal]
final readonly class PackmanFeatureFlags implements FeatureFlagsInterface
{
    public function __construct(private FeatureFlags $flags) {}

    public function define(FeatureFlagDefinition $def): void
    {
        $this->flags->define(new CoreFeatureFlagDefinition(
            key: $def->key,
            default: $def->default,
            module: $def->module,
            payload: $def->payload === null ? null : new JsonDocument($def->payload->canonicalJson),
        ));
    }
}
