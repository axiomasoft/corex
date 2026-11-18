<?php

declare(strict_types=1);

namespace CoreX\Access\Laravel;

use CoreX\Access\Access\Internal\DefaultAccessChecker;
use CoreX\Access\Access\Internal\DefaultScopeResolver;
use CoreX\Access\Access\Internal\EffectiveWorkspaceScope;
use CoreX\Access\Access\Internal\ScopeAvailability;
use CoreX\Access\Contracts\AccessChecker;
use CoreX\Access\Contracts\RoleMatrixCompiler;
use CoreX\Access\Matrix\Internal\DatabaseRoleMatrixCompiler;
use CoreX\Access\Matrix\Laravel\MatrixPortBindings;
use CoreX\Access\Roles\Internal\SystemRoleMutationScope;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Foundation\Application;
use Override;
use Packman\Attributes\Spi;
use Packman\Attributes\SpiKind;
use Packman\Foundation\Contracts\PackageRegistrar;

#[Spi(kind: SpiKind::Call, since: '1.0')]
final class AccessBindingsRegistrar implements PackageRegistrar
{
    #[Override]
    public function register(Application $app): void
    {
        MatrixPortBindings::register($app);

        $app->singleton(AuthOptions::class, static fn (Application $app): AuthOptions => AuthOptions::fromArray(
            (array) $app->make(Repository::class)->get('corex-access', []),
        ));
        $app->singleton(EffectiveWorkspaceScope::class, static fn (Application $app): EffectiveWorkspaceScope => EffectiveWorkspaceScope::resolve(
            options: $app->make(AuthOptions::class),
            container: $app,
        ));

        // scoped(), not singleton() — DefaultAccessChecker's scopeFor() memo
        // must not survive across requests on a reused worker (Octane).
        $app->scoped(AccessChecker::class, DefaultAccessChecker::class);

        // scoped() for the same reason: ScopeAvailability's per-(entity_key,
        // scope) memo (P2.9 D114) must not survive a reused Octane worker
        // either, and DefaultScopeResolver is resolved once per listing
        // query — binding it keeps that one ScopeAvailability instance (and
        // its memo) shared across every apply() call within one request.
        $app->scoped(ScopeAvailability::class);
        $app->scoped(SystemRoleMutationScope::class);
        $app->scoped(ScopeResolver::class, DefaultScopeResolver::class);

        // P2.21: PermissionsMerger's lifecycle listener needs this resolved
        // through the interface — nothing bound it before (P2.8/P2.9 always
        // constructed DatabaseRoleMatrixCompiler directly).
        $app->bind(RoleMatrixCompiler::class, DatabaseRoleMatrixCompiler::class);

    }

    #[Override]
    public function boot(Application $app): void
    {
        $app->make(AuthOptions::class);
    }
}
