<?php

declare(strict_types=1);

namespace CoreX\Errors\Laravel;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use CoreX\Errors\Contracts\ProblemRegistry;
use CoreX\Errors\Internal\CompiledProblemRegistry;
use CoreX\Modules\Contracts\PackageRegistrar;
use Illuminate\Contracts\Foundation\Application;
use Override;

/** Composition of the kernel module; the public provider delegates here. */
#[Spi(kind: SpiKind::Call, since: '1.0')]
final class ErrorsRegistrar implements PackageRegistrar
{
    #[Override]
    public function register(Application $app): void
    {
        $app->singleton(ProblemRegistry::class, static fn (Application $app): CompiledProblemRegistry => (new ProblemRegistryFactory)->make($app));
        $app->booted(static fn (Application $app) => $app->make(ProblemRegistry::class));
    }

    #[Override]
    public function boot(Application $app): void {}
}
