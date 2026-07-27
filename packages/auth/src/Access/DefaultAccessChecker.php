<?php

declare(strict_types=1);

namespace CoreX\Auth\Access;

use AzGuard\Models\DirectGrant;
use CoreX\Auth\Contracts\AccessChecker;
use CoreX\Auth\Contracts\Principal;
use CoreX\Auth\Enums\PrincipalType;
use CoreX\Auth\Enums\Scope;
use CoreX\Auth\Matrix\ScopeMapVersion;
use CoreX\Auth\RecordContext;
use CoreX\Models\Department;
use CoreX\Modules\Contracts\EntityRegistry;
use CoreX\Tenancy\Contracts\TenancyManager;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Override;

use function data_get;

/**
 * Two-phase AccessChecker (D101, B-11 §5.5). allowed() asks the real AzGuard
 * chain through PrincipalIdentity — never a private lookup (premortem C1's
 * round-trip); scopeFor()/canOnRecord() compile `aut_role_matrix` rows
 * directly. Fail-closed throughout (Code Guidance): an unresolved entity,
 * absent role row or missing department table never widens to "the whole
 * scope" — it denies.
 *
 * Bound `scoped` (CoreAuthServiceProvider) so the scopeFor() memo below is
 * request-scoped, never carried across requests on a reused worker.
 */
final class DefaultAccessChecker implements AccessChecker
{
    /** @var array<string, Scope> Keyed with every contributing role's ScopeMapVersion — a compile() bump changes the key, i.e. a guaranteed miss. */
    private array $scopeMemo = [];

    public function __construct(
        private readonly EntityRegistry $entities,
        private readonly TenancyManager $tenancyManager,
        private readonly ScopeMapVersion $scopeMapVersion,
    ) {}

    #[Override]
    public function allowed(Principal $p, string $entityKey, string $action): bool
    {
        $panel = $this->panel();

        return PrincipalIdentity::fromPrincipal($p)->hasPermission("{$panel}.{$entityKey}.{$action}", $panel);
    }

    #[Override]
    public function scopeFor(Principal $p, string $entityKey, string $action, ?RecordContext $ctx = null): Scope
    {
        $roleIds = $this->activeRoleIds($p);

        if ($roleIds === []) {
            return Scope::None;
        }

        $memoKey = $this->memoKey($p, $entityKey, $action, $ctx, $roleIds);

        if (array_key_exists($memoKey, $this->scopeMemo)) {
            return $this->scopeMemo[$memoKey];
        }

        $effective = $this->maxOf(
            $this->matrixQuery($roleIds, $entityKey, $action)->whereNull('context_type')->pluck('scope'),
        );

        if ($ctx instanceof RecordContext) {
            $contextRows = $this->matrixQuery($roleIds, $entityKey, $action)
                ->where('context_type', $ctx->type)
                ->where('context_id', $ctx->id)
                ->pluck('scope');

            if ($contextRows->isNotEmpty()) {
                $effective = $this->maxOf($contextRows);
            }
        }

        if ($this->degradesToNone($p->principalType(), $effective)) {
            $effective = Scope::None;
        }

        return $this->scopeMemo[$memoKey] = $effective;
    }

    #[Override]
    public function canOnRecord(Principal $p, string $action, object $record): bool
    {
        $entity = $this->entities->forModel($record::class);

        if ($entity === null) {
            return false;
        }

        $key = "{$this->panel()}.{$entity->handle}.{$action}";

        if ($this->allowed($p, $entity->handle, $action)
            && $this->matchesScope($this->scopeFor($p, $entity->handle, $action), $record, $p)) {
            return true;
        }

        return $this->hasActiveDirectGrant($p, $key);
    }

    private function matchesScope(Scope $scope, object $record, Principal $p): bool
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

    private function matchesWorkspace(object $record): bool
    {
        $recordWorkspaceId = data_get($record, 'workspace_id');

        if ($recordWorkspaceId === null) {
            return true;
        }

        return $recordWorkspaceId === $this->tenancyManager->context()?->workspace?->id;
    }

    /**
     * dept/dept_tree resolve the record owner's department against the
     * principal's own, via `users.department_id` (DB_SCHEMA §3.4) and
     * `sys_departments.path` (ltree). Absent in boxed profiles — no
     * `users`/`sys_departments` there (AC-28) — fails closed rather than
     * silently granting (Code Guidance if-then #3); a documented profile
     * limitation, not a bug.
     */
    private function matchesDepartment(object $record, Principal $p, bool $tree): bool
    {
        if ($p->principalType() !== PrincipalType::User) {
            return false;
        }

        if (! Schema::hasTable('users') || ! Schema::hasTable((new Department)->getTable())) {
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

        $principalPath = Department::query()->where('id', $principalDeptId)->value('path');

        if (! is_string($principalPath)) {
            return false;
        }

        return Department::query()
            ->where('id', $ownerDeptId)
            ->whereRaw('path <@ ?::ltree', [$principalPath])
            ->exists();
    }

    private function hasActiveDirectGrant(Principal $p, string $key): bool
    {
        return DirectGrant::query()
            ->where('grantable_type', $p->principalType()->value)
            ->where('grantable_id', $p->principalId())
            ->where('panel_id', $this->panel())
            ->where('permission_key', $key)
            ->active()
            ->exists();
    }

    /** @return list<string> */
    private function activeRoleIds(Principal $p): array
    {
        $workspaceId = $this->tenancyManager->context()?->workspace?->id;

        return DB::table('aut_role_assignments')
            ->where('principal_type', $p->principalType()->value)
            ->where('principal_id', $p->principalId())
            ->where(static function (Builder $q): void {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->where(static function (Builder $q) use ($workspaceId): void {
                $q->whereNull('workspace_id');

                if ($workspaceId !== null) {
                    $q->orWhere('workspace_id', $workspaceId);
                }
            })
            ->pluck('role_id')
            ->unique()
            ->values()
            ->all();
    }

    /** @param list<string> $roleIds */
    private function matrixQuery(array $roleIds, string $entityKey, string $action): Builder
    {
        return DB::table('aut_role_matrix')
            ->whereIn('role_id', $roleIds)
            ->where('entity_key', $entityKey)
            ->where('action', $action);
    }

    /** @param Collection<int, string> $values */
    private function maxOf(Collection $values): Scope
    {
        $winner = Scope::None;

        foreach ($values as $value) {
            $candidate = Scope::from($value);

            if ($candidate->weight() > $winner->weight()) {
                $winner = $candidate;
            }
        }

        return $winner;
    }

    private function degradesToNone(PrincipalType $type, Scope $scope): bool
    {
        return in_array($type, [PrincipalType::Agent, PrincipalType::ApiKey], true)
            && in_array($scope, [Scope::Own, Scope::Dept, Scope::DeptTree], true);
    }

    /** @param list<string> $roleIds */
    private function memoKey(Principal $p, string $entityKey, string $action, ?RecordContext $ctx, array $roleIds): string
    {
        $versionFingerprint = (new Collection($roleIds))
            ->map(fn (string $roleId): int => $this->scopeMapVersion->current($roleId))
            ->implode(',');

        return implode('|', [
            $p->principalType()->value,
            $p->principalId(),
            $entityKey,
            $action,
            $ctx->type ?? '',
            $ctx->id ?? '',
            $versionFingerprint,
        ]);
    }

    private function panel(): string
    {
        return (string) config('corex-auth.panel', 'app');
    }
}
