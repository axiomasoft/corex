<?php

declare(strict_types=1);

namespace CoreX\Tests\Stubs\Lifecycle;

use CoreX\Contracts\FeatureFlagDefinition;
use CoreX\Modules\CorexModule;
use CoreX\Modules\Manifest;

/**
 * No migrations/hooks — exists solely to exercise `Manifest::$featureFlags`
 * → `FeatureFlags::define()` on `enable()` (P1.25, A68).
 */
final class StubFeatureFlagModule extends CorexModule
{
    public function manifest(): Manifest
    {
        return Manifest::make('corex/stub-feature-flag')
            ->title('Stub Feature Flag')
            ->tablePrefix('sff')
            ->featureFlags([
                new FeatureFlagDefinition(key: 'sff.beta_mode', default: false, module: 'corex/stub-feature-flag'),
            ]);
    }
}
