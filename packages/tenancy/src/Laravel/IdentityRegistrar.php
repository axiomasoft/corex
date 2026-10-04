<?php

declare(strict_types=1);

namespace CoreX\Tenancy\Laravel;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use CoreX\Context\Contracts\IdentityPasswordRehasher;
use CoreX\Context\Contracts\TenancyManager;
use CoreX\Modules\Contracts\PackageRegistrar;
use CoreX\Modules\Laravel\RegistersNothingOnBoot;
use CoreX\Tenancy\Account\Contracts\AccountRead;
use CoreX\Tenancy\Identity\Contracts\IdentitySyncer;
use CoreX\Tenancy\Identity\Internal\DatabaseIdentitySyncer;
use CoreX\Tenancy\Laravel\Support\TenancyOptions;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\ConnectionResolverInterface;
use Override;
use Psr\Clock\ClockInterface;

#[Spi(kind: SpiKind::Call, since: '1.0')]
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
                $app->make(AccountRead::class),
                $app->make(ConnectionResolverInterface::class),
                $app->make(ClockInterface::class),
                $app->make(Dispatcher::class),
            ),
        );
        $app->singleton(IdentitySyncer::class, static fn (Application $app): DatabaseIdentitySyncer => $app->make(DatabaseIdentitySyncer::class));

        if (! $app->bound(IdentityPasswordRehasher::class)) {
            $app->singleton(IdentityPasswordRehasher::class, static fn (Application $app): DatabaseIdentitySyncer => $app->make(DatabaseIdentitySyncer::class));
        }
    }
}
