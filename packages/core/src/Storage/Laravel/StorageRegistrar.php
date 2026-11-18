<?php

declare(strict_types=1);

namespace CoreX\Storage\Laravel;

use CoreX\Config\Data\CoreOptions;
use CoreX\Context\Contracts\TenantContextResolver;
use CoreX\Storage\Contracts\TenantStorage;
use CoreX\Storage\Internal\LocalTenantStorage;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Filesystem\FilesystemManager;
use Override;
use Packman\Attributes\Spi;
use Packman\Attributes\SpiKind;
use Packman\Foundation\Contracts\PackageRegistrar;

/** Composition of the kernel module; the public provider delegates here. */
#[Spi(kind: SpiKind::Call, since: '1.0')]
final class StorageRegistrar implements PackageRegistrar
{
    #[Override]
    public function register(Application $app): void
    {
        $app->scoped(TenantStorage::class, static fn (Application $app): LocalTenantStorage => new LocalTenantStorage(
            contexts: $app->make(TenantContextResolver::class),
            filesystems: new FilesystemManager($app),
            durableRoot: $app->make(CoreOptions::class)->storageDurableRoot,
            ephemeralRoot: $app->make(CoreOptions::class)->storageEphemeralRoot,
        ));
    }

    #[Override]
    public function boot(Application $app): void {}
}
