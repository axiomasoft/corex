<?php

declare(strict_types=1);

namespace CoreX\Operations\Laravel;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use CoreX\Operations\Definition\Internal\OperationRegistry;
use CoreX\Operations\Laravel\Support\ApiOptions;
use CoreX\Operations\Pagination\Contracts\CursorCodec;
use CoreX\Operations\Pagination\Contracts\CursorKeyring;
use CoreX\Operations\Pagination\Internal\ConfiguredCursorKeyring;
use CoreX\Operations\Pagination\Internal\OrderingRegistry;
use CoreX\Operations\Pagination\Internal\SignedCursorCodec;
use Illuminate\Contracts\Foundation\Application;
use Override;
use Packman\Foundation\Contracts\PackageRegistrar;
use Packman\Modules\Contracts\Registry\ModuleRegistry;
use Psr\Clock\ClockInterface;

/** Composition of the operations package. */
#[Spi(kind: SpiKind::Call, since: '1.0')]
final class PaginationRegistrar implements PackageRegistrar
{
    #[Override]
    public function register(Application $app): void
    {
        $app->singleton(CursorKeyring::class, static fn (Application $app): CursorKeyring => new ConfiguredCursorKeyring($app->make(ApiOptions::class)));
        $app->singleton(SignedCursorCodec::class, static fn (Application $app): SignedCursorCodec => new SignedCursorCodec(
            clock: $app->make(ClockInterface::class),
            keyring: $app->make(CursorKeyring::class),
        ));
        $app->scoped(OrderingRegistry::class, static fn (Application $app): OrderingRegistry => OrderingRegistry::fromOperations(
            new OperationRegistry($app->make(ModuleRegistry::class))->all($app->make(ApiOptions::class)->defaultMajor),
        ));
        $app->alias(SignedCursorCodec::class, CursorCodec::class);
        $app->alias(OrderingRegistry::class, \CoreX\Operations\Pagination\Contracts\OrderingRegistry::class);
    }

    #[Override]
    public function boot(Application $app): void {}
}
