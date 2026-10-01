<?php

declare(strict_types=1);

namespace CoreX\Access\Access\Internal;

use CoreX\Access\Contracts\Principal;
use CoreX\Access\Enums\PrincipalType;
use CoreX\Access\Enums\Scope;
use CoreX\Access\Laravel\AuthOptions;
use CoreX\Context\Contracts\TenancyManager;
use CoreX\Context\Data\WorkspaceRef;
use CoreX\Departments\Laravel\DepartmentQueries;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

use function data_get;

/**
 * Single owner of the six-scope semantics (B-11 §5.5, D110): before this
 * class, `own`/`dept`/`dept_tree`/`workspace`/`account`/`none` were compiled
 * twice — once as a Builder WHERE (this class) and once as a per-record
 * check (the old `DefaultAccessChecker::matchesScope()`). Two copies of one
 * security semantic diverge not "if" but "when"; {@see DefaultAccessChecker}
 * now delegates its record check into {@see matches()} instead of carrying
 * its own copy, and a parity test asserts `applyTo()` and `matches()` agree
 * for every scope × PrincipalType (packages/access/tests/Pg/ScopeResolverTest.php).
 *
 * Depends on `TenancyManager`/`Schema`/`DB`/config only — never on
 * `AccessChecker` (that would cycle: AccessChecker → ScopeResolver →
 * AccessChecker, Code Guidance).
 *
 * @internal spec: B-11 §5.5, P2.9 D107/D108/D109/D110/D117
 */
final readonly class ScopePredicate
{
    public function __construct(
        private TenancyManager $tenancyManager,
        private AuthOptions $options,
    ) {}

    /**
     * @param  Builder<Model>  $q
     * @return Builder<Model>
     */
    public function applyTo(Builder $q, Scope $scope, Principal $p): Builder
    {
        return match ($scope) {
            Scope::None => $q->whereRaw('1 = 0'),
            Scope::Own => $q->where($q->getModel()->qualifyColumn('owner_id'), $p->principalId()),
            Scope::Dept => $this->applyDepartment($q, $p, tree: false),
            Scope::DeptTree => $this->applyDepartment($q, $p, tree: true),
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
            Scope::Dept => $this->matchesDepartment($record, $p, tree: false),
            Scope::DeptTree => $this->matchesDepartment($record, $p, tree: true),
        };
    }

    // ─── own/dept/dept_tree — Builder ──────────────────────────────────────

    /**
     * @param  Builder<Model>  $q
     * @return Builder<Model>
     */
    private function applyDepartment(Builder $q, Principal $p, bool $tree): Builder
    {
        if ($p->principalType() !== PrincipalType::User) {
            return $q->whereRaw('1 = 0');
        }

        if (! Schema::hasTable('users') || ! Schema::hasTable(DepartmentQueries::table())) {
            return $q->whereRaw('1 = 0');
        }

        $principalDeptId = DB::table('users')->where('id', $p->principalId())->value('department_id');

        if (! is_string($principalDeptId)) {
            return $q->whereRaw('1 = 0');
        }

        $column = $q->getModel()->qualifyColumn('owner_id');

        if (! $tree) {
            return $q->whereIn($column, DB::table('users')->where('department_id', $principalDeptId)->select('id'));
        }

        $principalPath = DepartmentQueries::path($principalDeptId);

        if (! is_string($principalPath)) {
            return $q->whereRaw('1 = 0');
        }

        // ONE `path <@` subquery (premortem A6) — no recursive traversal: the
        // department subtree resolves in the same statement as the owning
        // users lookup, never per-row.
        $deptIds = DepartmentQueries::subtreeIds($principalPath);

        return $q->whereIn($column, DB::table('users')->whereIn('department_id', $deptIds)->select('id'));
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

        if (! Schema::hasTable('users') || ! Schema::hasTable(DepartmentQueries::table())) {
            return false;
        }

        $ownerId = data_get($record, 'owner_id');

        if (! is_string($ownerId)) {
            return false;
        }

        $ownerDeptId = DB::table('users')->where('id', $ownerId)->value('department_id');
        $principalDeptId = DB::table('users')->where('id', $p->principalId())->value('department_id');

        if (! is_string($ownerDeptId) || ! is_string($principalDeptId)) {
            return false;
        }

        if (! $tree) {
            return $ownerDeptId === $principalDeptId;
        }

        $principalPath = DepartmentQueries::path($principalDeptId);

        if (! is_string($principalPath)) {
            return false;
        }

        return DepartmentQueries::contains($ownerDeptId, $principalPath);
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
            return $q->whereNull($column);
        }

        $visibleIds = $this->visibleWorkspaceIds($ctxWorkspace);

        return $q->where(function (Builder $query) use ($column, $visibleIds): void {
            $query->whereIn($column, $visibleIds)->orWhereNull($column);
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

        /** @var list<string> */
        return DB::table('wsp_workspaces')
            ->whereRaw('path <@ ?::ltree', [$ctxWorkspace->path])
            ->pluck('id')
            ->map(static fn (mixed $id): string => (string) $id)
            ->all();
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

        if (! Schema::hasTable('wsp_workspaces')) {
            return false;
        }

        $flag = DB::table('wsp_workspaces')
            ->where('id', $ctxWorkspace->id)
            ->selectRaw("settings->>'cascade_view' as cascade_view")
            ->value('cascade_view');

        return $flag === 'true';
    }

    // ─── account ────────────────────────────────────────────────────────────

    /**
     * No predicate of its own; additionally lifts the workspace global scope
     * PINNED by config (D109) — never `withoutGlobalScopes()`, which would
     * also strip SoftDeletes and any other unrelated scope a model carries.
     */
    /**
     * @param  Builder<Model>  $q
     * @return Builder<Model>
     */
    private function applyAccount(Builder $q): Builder
    {
        $scopeClass = $this->options->workspaceScopeClass;

        if (is_string($scopeClass) && $scopeClass !== '') {
            $q->withoutGlobalScope($scopeClass);
        }

        return $q;
    }
}
