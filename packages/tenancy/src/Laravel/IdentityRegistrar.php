<?php

declare(strict_types=1);

namespace CoreX\Tenancy\Laravel;

use CoreX\Contracts\IdentityPasswordRehasher;
use CoreX\Support\PackageRegistrar;
use CoreX\Support\RegistersNothingOnBoot;
use CoreX\Tenancy\Contracts\TenancyManager;
use CoreX\Tenancy\Identity\DatabaseIdentitySyncer;
use CoreX\Tenancy\Identity\IdentitySyncer;
use CoreX\Tenancy\Support\TenancyOptions;
use Illuminate\Contracts\Foundation\Application;
use Override;

/** @internal Package composition type. */
final class IdentityRegistrar implements PackageRegistrar
{
    use RegistersNothingOnBoot;

    #[Override]
    public function register(Application $app): void
    {
        $app->singleton(
            DatabaseIdentitySyncer::class,
            static fn (Application $app): DatabaseIdentitySyncer => new DatabaseIdentitySyncer(
                $app->make(TenancyOptions::class)->centralConnection,
                $app->make(TenancyManager::class),
            ),
        );
        $app->singleton(IdentitySyncer::class, static fn (Application $app): DatabaseIdentitySyncer => $app->make(DatabaseIdentitySyncer::class));

        if (! $app->bound(IdentityPasswordRehasher::class)) {
            $app->singleton(IdentityPasswordRehasher::class, static fn (Application $app): DatabaseIdentitySyncer => $app->make(DatabaseIdentitySyncer::class));
        }
    }
}
