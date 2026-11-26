<?php

declare(strict_types=1);

namespace CoreX\Access\Tests\Stubs;

use CoreX\Context\Contracts\TenancyManager;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Stand-in for `CoreX\Tenancy\Laravel\Scopes\WorkspaceScope` — unavailable here,
 * axiomasoft/corex-access does not depend on axiomasoft/corex-tenancy. Identical filter
 * (`workspace_id = ctx.workspace.id OR workspace_id IS NULL`), used to prove
 * WorkspaceScopeWiring registers a REAL global scope (not just a marker) and
 * that `scope=account` correctly lifts this specific class without
 * touching an unrelated scope on the same query.
 */
final class FakeWorkspaceScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $workspace = resolve(TenancyManager::class)->context()?->workspace;

        if ($workspace === null) {
            return;
        }

        $column = $model->qualifyColumn('workspace_id');

        $builder->where(static function (Builder $query) use ($column, $workspace): void {
            $query->where($column, $workspace->id)->orWhereNull($column);
        });
    }
}
