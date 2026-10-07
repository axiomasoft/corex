<?php

declare(strict_types=1);

namespace CoreX\Data\Laravel;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use CoreX\Config\Data\CoreOptions;
use CoreX\Config\Laravel\ConfiguredImplementation;
use CoreX\Data\Contracts\DataScopeAuthorizer;
use CoreX\Data\Contracts\DataScopeCodec;
use CoreX\Data\Internal\ArrayDataScopeCodec;
use CoreX\Modules\Contracts\PackageRegistrar;
use Illuminate\Contracts\Foundation\Application;
use Override;

/** Composition of the kernel module; the public provider delegates here. */
#[Spi(kind: SpiKind::Call, since: '1.0')]
final class DataRegistrar implements PackageRegistrar
{
    #[Override]
    public function register(Application $app): void
    {
        $app->singleton(DataScopeAuthorizer::class, static fn (Application $app): DataScopeAuthorizer => ConfiguredImplementation::resolve(app: $app, contract: DataScopeAuthorizer::class, implementation: $app->make(CoreOptions::class)->dataScopeAuthorizer));
        $app->singleton(DataScopeCodec::class, ArrayDataScopeCodec::class);
    }

    #[Override]
    public function boot(Application $app): void {}
}
