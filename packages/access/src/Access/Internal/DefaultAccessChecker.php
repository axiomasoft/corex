<?php

declare(strict_types=1);

namespace CoreX\Access\Access\Internal;

use CoreX\Access\Contracts\AccessChecker;
use CoreX\Access\Data\RecordContext;
use CoreX\Access\Enums\Scope;
use CoreX\Access\Laravel\AuthOptions;
use CoreX\Access\Matrix\Contracts\EntityActivity;
use CoreX\Access\Matrix\Contracts\ScopeMapVersions as ScopeMapVersion;
use CoreX\Context\Contracts\Principal;
use CoreX\Context\Contracts\TenancyManager;
use CoreX\Context\Enums\PrincipalType;
use Illuminate\Database\ConnectionResolverInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Override;
use Packman\Modules\Contracts\Entities\EntityRegistry;
use Psr\Clock\ClockInterface;
use UnexpectedValueException;

/**
 * Two-phase AccessChecker. Permissions come from the native role matrix and
 * direct grants. Fail-closed throughout: an unresolved or inactive entity,
 * absent role row or missing department table never widens to "the whole
 * scope" — it denies.
 *
 * The scope predicate itself (own/dept/dept_tree/workspace/account/none) is
 * NOT decided here (P2.9, D110): it is delegated to {@see ScopePredicate},
 * the single owner shared with the builder path
 * ({@see DefaultScopeResolver}), so the two paths cannot silently diverge.
 * Bound `scoped` (AccessServiceProvider) so the scopeFor() memo below is
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
        private readonly ConnectionResolverInterface $connections,
        private readonly ClockInterface $clock,
        private readonly EntityActivity $activity,
    ) {}

    #[Override]
    public function allowed(Principal $p, string $entityKey, string $action): bool
    {
        if (! $this->activity->isActive($entityKey)) {
            return false;
        }

        $roleIds = $this->activeRoleIds($p);

        if ($roleIds !== [] && $this->matrixQuery(roleIds: $roleIds, entityKey: $entityKey, action: $action)
            ->where('scope', '<>', Scope::None->value)->exists()) {
            return true;
        }

        return $this->directGrants->hasActive(p: $p, key: "{$this->panel()}.{$entityKey}.{$action}");
    }

    #[Override]
    public function scopeFor(Principal $p, string $entityKey, string $action, ?RecordContext $ctx = null): Scope
    {
        if (! $this->activity->isActive($entityKey)) {
            return Scope::None;
        }

        $roleIds = $this->activeRoleIds($p);

        if ($roleIds === []) {
            return Scope::None;
        }

        $memoKey = $this->memoKey(p: $p, entityKey: $entityKey, action: $action, ctx: $ctx, roleIds: $roleIds);

        if (array_key_exists($memoKey, $this->scopeMemo)) {
            return $this->scopeMemo[$memoKey];
        }

        $effective = $this->maxOf(
            $this->matrixQuery(roleIds: $roleIds, entityKey: $entityKey, action: $action)->whereNull('context_type')->pluck('scope'),
        );

        if ($ctx instanceof RecordContext) {
            $contextRows = $this->matrixQuery(roleIds: $roleIds, entityKey: $entityKey, action: $action)
                ->where('context_type', $ctx->type)
                ->where('context_id', $ctx->id)
                ->pluck('scope');

            if ($contextRows->isNotEmpty()) {
                $effective = $this->maxOf($contextRows);
            }
        }

        if ($this->degradesToNone(type: $p->principalType(), scope: $effective)) {
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

        if (! $this->activity->isActive($entity->handle)) {
            return false;
        }

        $key = "{$this->panel()}.{$entity->handle}.{$action}";

        if ($this->directGrants->hasActive(p: $p, key: $key)) {
            return true;
        }

        return $this->allowed(p: $p, entityKey: $entity->handle, action: $action)
            && $this->scopePredicate->matches(
                scope: new ScopeAvailability(entities: $this->entities, connections: $this->connections)->normalize(entityKey: $entity->handle, scope: $this->scopeFor(p: $p, entityKey: $entity->handle, action: $action)),
                record: $record,
                p: $p,
            );
    }

    /** @return list<string> */
    private function activeRoleIds(Principal $p): array
    {
        $workspaceId = $this->tenancyManager->context()?->workspace?->id;

        $connection = $this->connections->connection();
        $now = $this->clock->now();

        $values = $connection->table('aut_role_assignments')
            ->whereIn('role_id', $connection->table('aut_roles')->select('id')->whereNull('deleted_at'))
            ->where('principal_type', $p->principalType()->value)
            ->where('principal_id', $p->principalId())
            ->where(static function (Builder $q) use ($now): void {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', $now);
            })
            ->where(static function (Builder $q) use ($workspaceId): void {
                $q->whereNull('workspace_id');

                if ($workspaceId !== null) {
                    $q->orWhere('workspace_id', $workspaceId);
                }
            })
            ->pluck('role_id')
            ->unique()
            ->values();

        $roleIds = [];
        foreach ($values as $value) {
            if (! is_string($value)) {
                throw new UnexpectedValueException('Role assignment identifiers must be strings.');
            }
            $roleIds[] = $value;
        }

        return $roleIds;
    }

    /** @param list<string> $roleIds */
    private function matrixQuery(array $roleIds, string $entityKey, string $action): Builder
    {
        return $this->connections->connection()->table('aut_role_matrix')
            ->whereIn('role_id', $roleIds)
            ->where('entity_key', $entityKey)
            ->where('action', $action);
    }

    /** @param Collection<array-key, mixed> $values */
    private function maxOf(Collection $values): Scope
    {
        $winner = Scope::None;

        foreach ($values as $value) {
            if (! is_string($value)) {
                throw new UnexpectedValueException('Role matrix scopes must be strings.');
            }

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
