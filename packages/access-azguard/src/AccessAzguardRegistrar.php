<?php

declare(strict_types=1);

namespace CoreX\AccessAzguard;

use CoreX\Access\Contracts\AccessChecker;
use CoreX\Access\Laravel\ScopeResolver;
use CoreX\AccessAzguard\Contracts\AccessContextMapper;
use CoreX\AccessAzguard\Internal\AzGuardAccessChecker;
use CoreX\AccessAzguard\Internal\CoreXAccessContextMapper;
use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use Illuminate\Contracts\Foundation\Application;
use Packman\Foundation\Contracts\PackageRegistrar;

#[Spi(kind: SpiKind::Call, since: '1.0')]
final class AccessAzguardRegistrar implements PackageRegistrar
{
    public function register(Application $app): void
    {
        $app->bindIf(AccessContextMapper::class, CoreXAccessContextMapper::class);
        $app->scoped(AzGuardAccessChecker::class);
        $app->extend(AccessChecker::class, static fn (mixed $previous, Application $app): AzGuardAccessChecker => $app->make(AzGuardAccessChecker::class));
        $app->extend(ScopeResolver::class, static fn (mixed $previous, Application $app): AzGuardAccessChecker => $app->make(AzGuardAccessChecker::class));
    }

    public function boot(Application $app): void {}
}
