<?php

declare(strict_types=1);

namespace CoreX\Tenancy\Laravel;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use CoreX\Context\Laravel\WorkspaceScopeProvider;
use CoreX\Modules\Contracts\PackageRegistrar;
use CoreX\Modules\Laravel\RegistersNothingOnBoot;
use CoreX\Tenancy\Contracts\PrincipalWorkspaceResolver;
use CoreX\Tenancy\Workspace\Contracts\WorkspaceResolver;
use CoreX\Tenancy\Workspace\Internal\DatabaseWorkspaceResolver;
use CoreX\Tenancy\Workspace\Internal\TenancyWorkspaceScopeProvider;
use Illuminate\Contracts\Foundation\Application;
use Override;

#[Spi(kind: SpiKind::Call, since: '1.0')]
final class WorkspaceRegistrar implements PackageRegistrar
{
    use RegistersNothingOnBoot;

    #[Override]
    public function register(Application $app): void
    {
        // P2.23 (D136) — P2.6 left this interface unbound; no config-flagged
        // choice of implementation (a second one doesn't exist — a switch
        // key without a consumer is a fabricated contract, D116/D121).
        $app->singleton(WorkspaceResolver::class, DatabaseWorkspaceResolver::class);
        $app->bindIf(PrincipalWorkspaceResolver::class, DatabaseWorkspaceResolver::class);
        // Access wires/lifts this scope through the kernel contract (no class-name string).
        $app->singleton(WorkspaceScopeProvider::class, TenancyWorkspaceScopeProvider::class);
    }
}
