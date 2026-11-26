<?php

declare(strict_types=1);

namespace CoreX\Access\Access\Internal;

use Closure;
use CoreX\Access\AccessServiceProvider;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Packman\Attributes\Internal;
use Packman\Modules\Contracts\Entities\EntityRegistry;
use Packman\Modules\Enums\Entities\Capability;

/**
 * Auto-registers the workspace global scope on every model whose entity
 * declares `Capability::WorkspaceScoped`. Register the scope through
 * `Model::addGlobalScope()` during package boot.
 *
 * $scopeClass comes from {@see EffectiveWorkspaceScope} (fail-closed: a
 * configured class must exist). null ⇒ boxed profile without
 * axiomasoft/corex-tenancy, explicitly nothing to wire: boxed has no
 * workspaces, and the RBAC `workspace` scope ({@see ScopePredicate})
 * stays correct on its own.
 *
 * Called from {@see AccessServiceProvider::boot()}, the same
 * precedent as `CoreX\Audit\Internal\ModuleAuditHook`.
 *
 * @internal
 */
#[Internal]
final class WorkspaceScopeWiring
{
    /**
     * @param  class-string|null  $scopeClass
     * @param  Closure(class-string): Scope<Model>  $make  builds the scope instance (the caller owns the container).
     */
    public static function wire(EntityRegistry $registry, ?string $scopeClass, Closure $make): void
    {
        if ($scopeClass === null) {
            return;
        }

        foreach ($registry->byCapability(Capability::WorkspaceScoped) as $definition) {
            /** @var class-string<Model> $model */
            $model = $definition->model;

            $model::addGlobalScope($make($scopeClass));
        }
    }
}
