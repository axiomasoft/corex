<?php

declare(strict_types=1);

namespace CoreX\Access\Access\Internal;

use Closure;
use CoreX\Access\CoreAuthServiceProvider;
use CoreX\Modules\Contracts\EntityRegistry;
use CoreX\Modules\Enums\Capability;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Auto-registers the workspace global scope on every model whose entity
 * declares `Capability::WorkspaceScoped` (D115) — closes the P2.6 debt of
 * nobody ever calling `Model::addGlobalScope()` on `WorkspaceScope`. Not an
 * arch-test ("every workspace_scoped model must register it") on purpose:
 * the tree carries ZERO such entities today (rolling the capability out to
 * real product entities is out of this package's scope, D70/D91), so that
 * test would be green on an empty set — the same "gate green on nothing"
 * class this plan already caught twice (D84, D93). Automatic registration
 * makes the mismatch structurally impossible instead, proven by a stub
 * (positive + negative case).
 *
 * $scopeClass comes from {@see EffectiveWorkspaceScope} (fail-closed: a
 * configured class must exist). null ⇒ boxed profile without
 * axiomasoft/corex-tenancy (AC-28), explicitly nothing to wire: boxed has no
 * workspaces, and the RBAC `workspace` scope ({@see ScopePredicate}, D107)
 * stays correct on its own.
 *
 * Called from {@see CoreAuthServiceProvider::boot()}, the same
 * precedent as `CoreX\Modules\Internal\Audit\AuditCapabilityWiring`.
 *
 * @internal spec: B-11 §5.4 п.5, P2.9 D115
 */
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
