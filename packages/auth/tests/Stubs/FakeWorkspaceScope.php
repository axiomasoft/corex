<?php

declare(strict_types=1);

namespace CoreX\Auth\Tests\Stubs;

use CoreX\Tenancy\Contracts\TenancyManager;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Stand-in for `CoreX\Tenancy\Scopes\WorkspaceScope` — unavailable here,
 * corex/auth does not depend on corex/tenancy (AC-28). Identical filter
 * (`workspace_id = ctx.workspace.id OR workspace_id IS NULL`), used to prove
 * WorkspaceScopeWiring registers a REAL global scope (not just a marker) and
 * that `scope=account` correctly lifts this specific class (D109) without
 * touching an unrelated scope on the same query.
 */
final class FakeWorkspaceScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $workspace = app(TenancyManager::class)->context()?->workspace;

        if ($workspace === null) {
            return;
        }

        $column = $model->qualifyColumn('workspace_id');

        $builder->where(static function (Builder $query) use ($column, $workspace): void {
            $query->where($column, $workspace->id)->orWhereNull($column);
        });
    }
}
