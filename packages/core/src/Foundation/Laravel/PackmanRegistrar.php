<?php

declare(strict_types=1);

namespace CoreX\Foundation\Laravel;

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
use Packman\Modules\Contracts\Entities\EntityRegistryHook;

/** CoreX's domain adapters for the Packman module runtime. */
#[Spi(kind: SpiKind::Call, since: '1.0')]
final class PackmanRegistrar implements PackageRegistrar
{
    use RegistersNothingOnBoot;

    /** @var list<string> */
    private const array DEFAULT_PACKAGE_TYPES = [
        'corex-module',
        'corex-plugin',
        'packman-module',
        'packman-plugin',
        'module',
        'plugin',
    ];

    /** @var list<string> */
    private const array UPSTREAM_PACKAGE_TYPES = [
        'packman-module',
        'packman-plugin',
        'module',
        'plugin',
    ];

    /** @var list<list<string>> */
    private const array DEFAULT_EXTRA_KEYS = [
        ['corex', 'module'],
        ['corex', 'plugin'],
        ['packman', 'module'],
        ['packman', 'plugin'],
    ];

    /** @var list<list<string>> */
    private const array UPSTREAM_EXTRA_KEYS = [
        ['packman', 'module'],
        ['packman', 'plugin'],
        ['module'],
        ['plugin'],
    ];

    #[Override]
    public function register(Application $app): void
    {
        $config = $app->make(Repository::class);
        $packageTypes = $config->get('modules.package_types');

        if ($packageTypes === null || $packageTypes === self::UPSTREAM_PACKAGE_TYPES) {
            $config->set('modules.package_types', self::DEFAULT_PACKAGE_TYPES);
        }

        $extraKeys = $config->get('modules.extra_keys');

        if ($extraKeys === null || $extraKeys === self::UPSTREAM_EXTRA_KEYS) {
            $config->set('modules.extra_keys', self::DEFAULT_EXTRA_KEYS);
        }

        $app->singleton(ContextResolverInterface::class, PackmanContextResolver::class);
        $app->singleton(ActorResolverInterface::class, PackmanActorResolver::class);
        $app->singleton(FeatureFlagsInterface::class, PackmanFeatureFlags::class);
        $app->tag([ModuleAuditHook::class], EntityRegistryHook::class);
    }
}
