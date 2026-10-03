<?php

declare(strict_types=1);

namespace CoreX\Access\Laravel;

use AzGuard\Contracts\ContextGuard;
use AzGuard\Contracts\PermissionLayer;
use CoreX\Access\Access\Internal\DefaultAccessChecker;
use CoreX\Access\Access\Internal\DefaultScopeResolver;
use CoreX\Access\Access\Internal\ScopeAvailability;
use CoreX\Access\Access\Internal\WorkspacePermissionLayer;
use CoreX\Access\Contracts\AccessChecker;
use CoreX\Access\Contracts\RoleMatrixCompiler;
use CoreX\Access\Extend\Internal\PermissionsMerger;
use CoreX\Access\Matrix\Internal\DatabaseRoleMatrixCompiler;
use CoreX\Access\Matrix\Laravel\MatrixPortBindings;
use CoreX\Access\Roles\Internal\SystemRoleMutationScope;
use CoreX\Access\Tenancy\Internal\TenantContextGuard;
use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use CoreX\Modules\Contracts\PackageRegistrar;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Foundation\Application;
use Override;

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

        // The wiring below decides what to register, so it needs the shape at register time; this one-off read does not cache Options before boot.
        $options = AuthOptions::fromArray((array) $app->make(Repository::class)->get('corex-access', []));

        // Provide AzGuard's optional ContextGuard binding (ADR-009): contextual
        // checks (hasPermissionIn) resolve entities via axiomasoft/corex's registry.
        $app->singleton(ContextGuard::class, TenantContextGuard::class);

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

        // The PermissionLayer slot is singular (findings/P2.8-azguard-
        // inventory-2026-07-26.md §6) — the config switch (D101 risk note)
        // lets a target build step aside for another package's layer instead
        // of conflicting over it.
        if ($options->bindPermissionLayer) {
            $app->bind(PermissionLayer::class, WorkspacePermissionLayer::class);
        }

        $this->applyAzGuardPins($app, $options);
    }

    #[Override]
    public function boot(Application $app): void
    {
        $app->make(AuthOptions::class);
    }

    /**
     * axiomasoft/corex-access does not fork AzGuard (D15): the config pins that make
     * AzGuard line up with DB_SCHEMA §3.5 / CoreX's uuid principals (D104)
     * are applied onto AzGuard's own config here, sourced from OUR
     * publishable corex-access config — never a second copy of the values.
     * AzGuard's migrations read this config at `migrate` time (findings/
     * P2.8-azguard-inventory-2026-07-26.md §2), so setting it in register()
     * — well before any migrate call — is early enough regardless of
     * provider registration order.
     */
    private function applyAzGuardPins(Application $app, AuthOptions $options): void
    {
        $config = $app->make(Repository::class);
        $pins = $options->azguardPins;
        $tableNames = is_array($pins['table_names'] ?? null) ? $pins['table_names'] : [];
        $columnNames = is_array($pins['column_names'] ?? null) ? $pins['column_names'] : [];
        $models = is_array($pins['models'] ?? null) ? $pins['models'] : [];

        $config->set('az-guard.table_names.roles', $tableNames['roles'] ?? 'az_guard_roles');
        $config->set('az-guard.column_names.morph_type', $columnNames['morph_type'] ?? 'uuid');
        $config->set('az-guard.models.role', $models['role'] ?? null);
    }
}
