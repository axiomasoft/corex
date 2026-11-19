<?php

declare(strict_types=1);

namespace CoreX\Foundation\Laravel;

use CoreX\Audit\Internal\CurrentActor;
use CoreX\Audit\Internal\ModuleAuditHook;
use CoreX\Foundation\Internal\PackmanActorResolver;
use CoreX\Foundation\Internal\PackmanContextResolver;
use CoreX\Foundation\Internal\PackmanFeatureFlags;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Foundation\Application;
use Override;
use Packman\Attributes\Spi;
use Packman\Attributes\SpiKind;
use Packman\Context\Contracts\ActorResolverInterface;
use Packman\Context\Contracts\ContextResolverInterface;
use Packman\Features\Contracts\FeatureFlagsInterface;
use Packman\Foundation\Concerns\RegistersNothingOnBoot;
use Packman\Foundation\Contracts\PackageRegistrar;
use Packman\Modules\Contracts\Definition\ModuleDiscovery;
use Packman\Modules\Contracts\Entities\EntityRegistryHook;

/** CoreX's domain adapters for the Packman module runtime. */
#[Spi(kind: SpiKind::Call, since: '1.0')]
final class PackmanRegistrar implements PackageRegistrar
{
    use RegistersNothingOnBoot;

    #[Override]
    public function register(Application $app): void
    {
        $config = $app->make(Repository::class);
        $packageTypes = $config->get('modules.package_types');

        if ($packageTypes === null || $packageTypes === ModuleDiscovery::DEFAULT_PACKAGE_TYPES) {
            $config->set('modules.package_types', ['corex-module', 'corex-plugin', ...ModuleDiscovery::DEFAULT_PACKAGE_TYPES]);
        }

        $extraKeys = $config->get('modules.extra_keys');

        if ($extraKeys === null || $extraKeys === ModuleDiscovery::DEFAULT_EXTRA_KEYS) {
            $config->set('modules.extra_keys', [['corex', 'module'], ['corex', 'plugin'], ...ModuleDiscovery::DEFAULT_EXTRA_KEYS]);
        }

        $app->singleton(ContextResolverInterface::class, PackmanContextResolver::class);
        $app->scoped(ActorResolverInterface::class, static fn (Application $app): PackmanActorResolver => new PackmanActorResolver(
            static fn (): CurrentActor => $app->make(CurrentActor::class),
        ));
        $app->singleton(FeatureFlagsInterface::class, PackmanFeatureFlags::class);
        $app->tag([ModuleAuditHook::class], EntityRegistryHook::class);
    }
}
