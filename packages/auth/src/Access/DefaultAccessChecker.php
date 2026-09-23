<?php

declare(strict_types=1);

namespace CoreX\Auth\Access;

use CoreX\Auth\Contracts\AccessChecker;
use CoreX\Auth\Contracts\Principal;
use CoreX\Auth\Enums\PrincipalType;
use CoreX\Auth\Enums\Scope;
use CoreX\Auth\Matrix\ScopeMapVersion;
use CoreX\Auth\RecordContext;
use CoreX\Auth\Support\AuthOptions;
use CoreX\Modules\Contracts\EntityRegistry;
use CoreX\Tenancy\Contracts\TenancyManager;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Override;

/**
 * Two-phase AccessChecker (D101, B-11 §5.5). allowed() asks the real AzGuard
 * chain through PrincipalIdentity — never a private lookup (premortem C1's
 * round-trip); scopeFor()/canOnRecord() compile `aut_role_matrix` rows
 * directly. Fail-closed throughout (Code Guidance): an unresolved entity,
 * absent role row or missing department table never widens to "the whole
 * scope" — it denies.
 *
 * The scope predicate itself (own/dept/dept_tree/workspace/account/none) is
 * NOT decided here (P2.9, D110): it is delegated to {@see ScopePredicate},
 * the single owner shared with the builder path
 * ({@see DefaultScopeResolver}), so the two paths cannot silently diverge.
 * This class's own observable behaviour is UNCHANGED by that delegation —
 * its 20 pre-existing tests assert that.
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
        private readonly ScopePredicate $scopePredicate,
        private readonly DirectGrantLookup $directGrants,
        private readonly AuthOptions $options,
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

        if ($this->directGrants->hasActive($p, $key)) {
            return true;
        }

        return $this->allowed($p, $entity->handle, $action)
            && $this->scopePredicate->matches(
                (new ScopeAvailability($this->entities))->normalize($entity->handle, $this->scopeFor($p, $entity->handle, $action)),
                $record,
                $p,
            );
    }

    /** @return list<string> */
    private function activeRoleIds(Principal $p): array
    {
        $workspaceId = $this->tenancyManager->context()?->workspace?->id;

        return DB::table('aut_role_assignments')
            ->whereIn('role_id', DB::table('aut_roles')->select('id')->whereNull('deleted_at'))
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
        sort($roleIds);
        $versionFingerprint = array_map(fn (string $roleId): array => [$roleId, $this->scopeMapVersion->current($roleId)], $roleIds);

        return json_encode([
            $this->tenancyManager->context()?->account->id,
            $this->tenancyManager->context()?->workspace?->id,
            $this->panel(),
            $p->principalType()->value,
            $p->principalId(),
            $entityKey,
            $action,
            $ctx->type ?? '',
            $ctx->id ?? '',
            $versionFingerprint,
        ], JSON_THROW_ON_ERROR);
    }

    private function panel(): string
    {
        return $this->options->panel;
    }
}
