<?php

declare(strict_types=1);

namespace CoreX\Tests\Stubs\Lifecycle;

use Packman\Features\Data\FeatureFlagDefinition;
use Packman\Modules\Contracts\Definition\ModuleDefinition;
use Packman\Modules\Data\Lifecycle\ModuleManifest;

/**
 * No migrations/hooks — exists solely to exercise `ModuleManifest::$featureFlags`
 * → `FeatureFlags::define()` on `enable()` (P1.25, A68).
 */
final class StubFeatureFlagModule extends ModuleDefinition
{
    public function manifest(): ModuleManifest
    {
        return ModuleManifest::make('corex/stub-feature-flag')
            ->title('Stub Feature Flag')
            ->tablePrefix('sff')
            ->featureFlags([
                new FeatureFlagDefinition(key: 'sff.beta_mode', default: false, module: 'corex/stub-feature-flag'),
            ]);
    }
}
