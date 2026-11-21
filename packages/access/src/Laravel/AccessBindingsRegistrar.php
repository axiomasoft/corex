<?php

declare(strict_types=1);

namespace CoreX\Access\Laravel;

use CoreX\Access\Access\Internal\EffectiveWorkspaceScope;
use CoreX\Access\Access\Internal\MatrixPermissions;
use CoreX\Access\Access\Internal\PanelAccessChecker;
use CoreX\Access\Access\Internal\ScopeAvailability;
use CoreX\Access\Access\Internal\ScopedFieldPolicy;
use CoreX\Access\Contracts\AccessChecker;
use CoreX\Access\Contracts\RoleMatrixCompiler;
use CoreX\Access\Internal\AccessModuleReferences;
use CoreX\Access\Matrix\Internal\DatabaseRoleMatrixCompiler;
use CoreX\Access\Matrix\Laravel\MatrixPortBindings;
use CoreX\Access\Roles\DirectGrants;
use CoreX\Access\Roles\Internal\DatabaseDirectGrants;
use CoreX\Access\Roles\Internal\DatabaseRoleAdministration;
use CoreX\Access\Roles\Internal\SystemRoleMutationScope;
use CoreX\Access\Roles\RoleAdministration;
use CoreX\Data\Contracts\DataScopeAuthorizer;
use CoreX\Data\Contracts\FieldUsePolicy;
use CoreX\Foundation\Contracts\ModuleReferences;
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
        $app->scoped(AccessModuleReferences::class);
        $app->tag(AccessModuleReferences::class, ModuleReferences::class);

        MatrixPortBindings::register($app);

        $app->singleton(AuthOptions::class, static fn (Application $app): AuthOptions => AuthOptions::fromArray(
            (array) $app->make(Repository::class)->get('corex-access', []),
        ));
        $app->singleton(EffectiveWorkspaceScope::class, static fn (Application $app): EffectiveWorkspaceScope => EffectiveWorkspaceScope::resolve(
            options: $app->make(AuthOptions::class),
            container: $app,
        ));

        $app->scoped(ScopedFieldPolicy::class);
        $app->alias(ScopedFieldPolicy::class, DataScopeAuthorizer::class);
        $app->alias(ScopedFieldPolicy::class, FieldUsePolicy::class);
        $app->scoped(MatrixPermissions::class);
        $app->scoped(PanelAccessChecker::class);
        $app->alias(PanelAccessChecker::class, AccessChecker::class);
        $app->alias(PanelAccessChecker::class, ScopeResolver::class);
        $app->scoped(ScopeAvailability::class);
        $app->scoped(SystemRoleMutationScope::class);
        $app->bind(RoleAdministration::class, DatabaseRoleAdministration::class);
        $app->bind(DirectGrants::class, DatabaseDirectGrants::class);
        $app->bind(RoleMatrixCompiler::class, DatabaseRoleMatrixCompiler::class);

    }

    #[Override]
    public function boot(Application $app): void
    {
        $app->make(AuthOptions::class);
    }
}
