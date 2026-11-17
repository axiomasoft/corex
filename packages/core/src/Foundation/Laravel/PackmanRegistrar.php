<?php

declare(strict_types=1);

namespace CoreX\Foundation\Laravel;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use CoreX\Audit\Internal\ModuleAuditHook;
use CoreX\Foundation\Internal\PackmanActorResolver;
use CoreX\Foundation\Internal\PackmanContextResolver;
use CoreX\Foundation\Internal\PackmanFeatureFlags;
use Illuminate\Contracts\Foundation\Application;
use Packman\Context\Contracts\ActorResolverInterface;
use Packman\Context\Contracts\ContextResolverInterface;
use Packman\Features\Contracts\FeatureFlagsInterface;
use Packman\Foundation\Concerns\RegistersNothingOnBoot;
use Packman\Foundation\Contracts\PackageRegistrar;
use Packman\Modules\Contracts\Entities\EntityRegistryHook;

/** CoreX's domain adapters for the Packman module runtime. */
#[Spi(kind: SpiKind::Call, since: '1.0')]
final class PackmanRegistrar implements PackageRegistrar
{
    use RegistersNothingOnBoot;

    public function register(Application $app): void
    {
        $app->singleton(ContextResolverInterface::class, PackmanContextResolver::class);
        $app->singleton(ActorResolverInterface::class, PackmanActorResolver::class);
        $app->singleton(FeatureFlagsInterface::class, PackmanFeatureFlags::class);
        $app->tag([ModuleAuditHook::class], EntityRegistryHook::class);
    }
}
