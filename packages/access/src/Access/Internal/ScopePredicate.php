<?php

declare(strict_types=1);

namespace CoreX\Access\Access\Internal;

use CoreX\Access\Enums\Scope;
use CoreX\Context\Contracts\Principal;
use CoreX\Context\Contracts\TenancyManager;
use CoreX\Context\Data\WorkspaceRef;
use CoreX\Context\Enums\PrincipalType;
use CoreX\Departments\Laravel\DepartmentQueries;
use Illuminate\Database\Connection;
use Illuminate\Database\ConnectionResolverInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Database\Schema\Builder as SchemaBuilder;
use Packman\Attributes\Internal;
use UnexpectedValueException;

use function data_get;

/** Applies CoreX matrix scope to a record or its Eloquent query in the active tenant. */
#[Internal]
final readonly class ScopePredicate
{
    public function __construct(
        private TenancyManager $tenancyManager,
        private EffectiveWorkspaceScope $workspaceScope,
        private ConnectionResolverInterface $connections,
    ) {}

    /**
     * @param  Builder<Model>  $q
     * @return Builder<Model>
     */
    public function applyTo(Builder $q, Scope $scope, Principal $p): Builder
    {
        return match ($scope) {
            Scope::None => $this->applyNone($q),
            Scope::Own => $q->where($q->getModel()->qualifyColumn('owner_id'), $p->principalId()),
            Scope::Dept => $this->applyDepartment(q: $q, p: $p, tree: false),
            Scope::DeptTree => $this->applyDepartment(q: $q, p: $p, tree: true),
            Scope::Workspace => $this->applyWorkspace($q),
            Scope::Account => $this->applyAccount($q),
        };
    }

    public function matches(Scope $scope, object $record, Principal $p): bool
    {
        return match ($scope) {
            Scope::None => false,
            Scope::Account => true,
            Scope::Workspace => $this->matchesWorkspace($record),
            Scope::Own => data_get($record, 'owner_id') === $p->principalId(),
            Scope::Dept => $this->matchesDepartment(record: $record, p: $p, tree: false),
            Scope::DeptTree => $this->matchesDepartment(record: $record, p: $p, tree: true),
        };
    }

    // ─── own/dept/dept_tree — Builder ──────────────────────────────────────

    /**
     * @param  Builder<Model>  $q
     * @return Builder<Model>
     */
    private function applyNone(Builder $q): Builder
    {
        $q->getQuery()->whereRaw('1 = 0');

        return $q;
    }

    /**
     * @param  Builder<Model>  $q
     * @return Builder<Model>
     */
    private function applyDepartment(Builder $q, Principal $p, bool $tree): Builder
    {
        if ($p->principalType() !== PrincipalType::User) {
            return $this->applyNone($q);
        }

        if (! $this->schema()->hasTable('users') || ! $this->schema()->hasTable(DepartmentQueries::table())) {
            return $this->applyNone($q);
        }

        $principalDeptId = $this->connections->connection()->table('users')->where('id', $p->principalId())->value('department_id');

        if (! is_string($principalDeptId)) {
            return $this->applyNone($q);
        }

        $column = $q->getModel()->qualifyColumn('owner_id');

        if (! $tree) {
            $q->getQuery()->whereIn($column, $this->connections->connection()->table('users')->where('department_id', $principalDeptId)->select('id'));

            return $q;
        }

        $principalPath = DepartmentQueries::path($principalDeptId);

        if (! is_string($principalPath)) {
            return $this->applyNone($q);
        }

        // ONE `path <@` subquery (premortem A6) — no recursive traversal: the
        // department subtree resolves in the same statement as the owning
        // users lookup, never per-row.
        $deptIds = DepartmentQueries::subtreeIds($principalPath);

        $q->getQuery()->whereIn($column, $this->connections->connection()->table('users')->whereIn('department_id', $deptIds)->select('id'));

        return $q;
    }

    /**
     * dept/dept_tree resolve the record owner's department against the
     * principal's own, via `users.department_id` (DB_SCHEMA §3.4) and
     * `sys_departments.path` (ltree). Absent in boxed profiles — no
     * `users`/`sys_departments` there (AC-28) — fails closed rather than
     * silently granting.
     */
    private function matchesDepartment(object $record, Principal $p, bool $tree): bool
    {
        if ($p->principalType() !== PrincipalType::User) {
            return false;
        }

        if (! $this->schema()->hasTable('users') || ! $this->schema()->hasTable(DepartmentQueries::table())) {
            return false;
        }

        $ownerId = data_get($record, 'owner_id');

        if (! is_string($ownerId)) {
            return false;
        }

        $connection = $this->connections->connection();

        // One statement per check: the owner and principal rows (and, for the
        // tree, both departments) are joined instead of fetched one by one.
        if (! $tree) {
            return $connection->table('users as owner')
                ->join('users as principal', 'principal.department_id', '=', 'owner.department_id')
                ->where('owner.id', $ownerId)
                ->where('principal.id', $p->principalId())
                ->exists();
        }

        $departments = DepartmentQueries::table();

        // Both departments must be live: a removed department drops out of
        // scope, as on the builder path.
        return $connection->table('users as owner')
            ->join($departments.' as owner_department', static fn (JoinClause $join): JoinClause => $join
                ->on('owner_department.id', '=', 'owner.department_id')
                ->whereNull('owner_department.deleted_at'))
            ->join('users as principal', static fn (JoinClause $join): JoinClause => $join->where('principal.id', '=', $p->principalId()))
            ->join($departments.' as principal_department', static fn (JoinClause $join): JoinClause => $join
                ->on('principal_department.id', '=', 'principal.department_id')
                ->whereNull('principal_department.deleted_at'))
            ->where('owner.id', $ownerId)
            ->whereRaw('owner_department.path <@ principal_department.path')
            ->exists();
    }

    // ─── workspace — Builder + record ──────────────────────────────────────

    /**
     * @param  Builder<Model>  $q
     * @return Builder<Model>
     */
    private function applyWorkspace(Builder $q): Builder
    {
        $column = $q->getModel()->qualifyColumn('workspace_id');
        $ctxWorkspace = $this->tenancyManager->context()?->workspace;

        // No workspace narrowed in context: only account-wide rows are
        // visible (D107) — mirrors matchesWorkspace()'s own no-context denial
        // of a workspace-carrying record.
        if ($ctxWorkspace === null) {
            $q->getQuery()->whereNull($column);

            return $q;
        }

        $visibleIds = $this->visibleWorkspaceIds($ctxWorkspace);

        return $q->where(function (Builder $query) use ($column, $visibleIds): void {
            $query->getQuery()->whereIn($column, $visibleIds)->orWhereNull($column);
        });
    }

    /**
     * `workspace` carries an EXPLICIT predicate (D107) rather than relying on
     * `WorkspaceScope` already being applied — that global scope is not
     * registered on any model in the tree today, and even once
     * {@see WorkspaceScopeWiring} wires it, a double identical WHERE is
     * harmless. No context ⇒ only account-wide (`workspace_id IS NULL`)
     * records are visible — never all of them.
     */
    private function matchesWorkspace(object $record): bool
    {
        $recordWorkspaceId = data_get($record, 'workspace_id');

        if ($recordWorkspaceId === null) {
            return true;
        }

        $ctxWorkspace = $this->tenancyManager->context()?->workspace;

        if ($ctxWorkspace === null) {
            return false;
        }

        return in_array($recordWorkspaceId, $this->visibleWorkspaceIds($ctxWorkspace), true);
    }

    /**
     * `[ctxWorkspace->id]` by default; the ctx workspace's own subtree
     * (`path <@`) when its `settings->>'cascade_view'` is strictly `'true'`
     * (D117) — a hierarchical "parent sees children" view, never the reverse
     * (cascade always reads DOWN from the ctx workspace's own path).
     *
     * @return list<string>
     */
    private function visibleWorkspaceIds(WorkspaceRef $ctxWorkspace): array
    {
        if (! $this->cascadeEnabled($ctxWorkspace)) {
            return [$ctxWorkspace->id];
        }

        $values = $this->connections->connection()->table('wsp_workspaces')
            ->whereRaw('path <@ ?::ltree', [$ctxWorkspace->path])
            ->pluck('id');

        $ids = [];
        foreach ($values as $id) {
            if (! is_string($id)) {
                throw new UnexpectedValueException('Workspace identifiers must be strings.');
            }
            $ids[] = $id;
        }

        return $ids;
    }

    /**
     * Fail-closed by construction (D117): no `wsp_workspaces` table (boxed
     * profile, AC-28) · no row for the ctx workspace · flag absent or not
     * strictly `'true'` · empty `path` ⇒ cascade off. Default `settings =
     * '{}'` (DDL P2.6) means cascade is off unless explicitly turned on.
     */
    private function cascadeEnabled(WorkspaceRef $ctxWorkspace): bool
    {
        if ($ctxWorkspace->path === '') {
            return false;
        }

        if (! $this->schema()->hasTable('wsp_workspaces')) {
            return false;
        }

        $flag = $this->connections->connection()->table('wsp_workspaces')
            ->where('id', $ctxWorkspace->id)
            ->selectRaw("settings->>'cascade_view' as cascade_view")
            ->value('cascade_view');

        return $flag === 'true';
    }

    // ─── account ────────────────────────────────────────────────────────────

    /**
     * No predicate of its own; additionally lifts the workspace global scope
     * resolved by EffectiveWorkspaceScope (D109) — never `withoutGlobalScopes()`, which would
     * also strip SoftDeletes and any other unrelated scope a model carries.
     */
    /**
     * @param  Builder<Model>  $q
     * @return Builder<Model>
     */
    private function applyAccount(Builder $q): Builder
    {
        $scopeClass = $this->workspaceScope->class;

        if ($scopeClass !== null) {
            $q->withoutGlobalScope($scopeClass);
        }

        return $q;
    }

    private function schema(): SchemaBuilder
    {
        /** @var Connection */
        $connection = $this->connections->connection();

        return $connection->getSchemaBuilder();
    }
}
