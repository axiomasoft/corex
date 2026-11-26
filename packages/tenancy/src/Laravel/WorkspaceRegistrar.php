<?php

declare(strict_types=1);

namespace CoreX\Tenancy\Laravel;

use CoreX\Context\Laravel\WorkspaceScopeProvider;
use CoreX\Tenancy\Contracts\PrincipalWorkspaceResolver;
use CoreX\Tenancy\Workspace\Contracts\WorkspaceResolver;
use CoreX\Tenancy\Workspace\Internal\DatabaseWorkspaceResolver;
use CoreX\Tenancy\Workspace\Internal\TenancyWorkspaceScopeProvider;
use Illuminate\Contracts\Foundation\Application;
use Override;
use Packman\Attributes\Spi;
use Packman\Attributes\SpiKind;
use Packman\Foundation\Concerns\RegistersNothingOnBoot;
use Packman\Foundation\Contracts\PackageRegistrar;

#[Spi(kind: SpiKind::Call, since: '1.0')]
final class WorkspaceRegistrar implements PackageRegistrar
{
    use RegistersNothingOnBoot;

    #[Override]
    public function register(Application $app): void
    {
        // Resolve workspaces through the database implementation.
        $app->singleton(WorkspaceResolver::class, DatabaseWorkspaceResolver::class);
        $app->bindIf(PrincipalWorkspaceResolver::class, DatabaseWorkspaceResolver::class);
        // Access wires/lifts this scope through the kernel contract (no class-name string).
        $app->singleton(WorkspaceScopeProvider::class, TenancyWorkspaceScopeProvider::class);
    }
}
