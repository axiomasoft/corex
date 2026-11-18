<?php

declare(strict_types=1);

namespace CoreX\Features\Laravel;

use CoreX\Config\Data\CoreOptions;
use CoreX\Features\Contracts\FeatureFlags;
use Illuminate\Contracts\Foundation\Application;
use Override;
use Packman\Attributes\Spi;
use Packman\Attributes\SpiKind;
use Packman\Config\Laravel\ConfiguredImplementation;
use Packman\Foundation\Contracts\PackageRegistrar;

/** Composition of the kernel module; the public provider delegates here. */
#[Spi(kind: SpiKind::Call, since: '1.0')]
final class FeaturesRegistrar implements PackageRegistrar
{
    #[Override]
    public function register(Application $app): void
    {
        $app->singleton(FeatureFlags::class, static fn (Application $app): FeatureFlags => ConfiguredImplementation::resolve(app: $app, contract: FeatureFlags::class, implementation: $app->make(CoreOptions::class)->featureFlags));
    }

    #[Override]
    public function boot(Application $app): void {}
}
