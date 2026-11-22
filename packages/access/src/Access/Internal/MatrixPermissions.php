<?php

declare(strict_types=1);

namespace CoreX\Access\Access\Internal;

use Closure;
use CoreX\Access\Data\RecordContext;
use CoreX\Access\Enums\Scope;
use CoreX\Access\Laravel\AuthOptions;
use CoreX\Access\Matrix\Contracts\EntityActivity;
use CoreX\Access\Matrix\Contracts\ScopeMapVersions as ScopeMapVersion;
use CoreX\Context\Contracts\Principal;
use CoreX\Context\Contracts\TenancyManager;
use CoreX\Context\Enums\PrincipalType;
use DateTimeImmutable;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\ConnectionResolverInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Packman\Attributes\Internal;
use Psr\Clock\ClockInterface;
use UnexpectedValueException;

/** Product role assignments and direct grants inside the current tenant connection. */
#[Internal]
final class MatrixPermissions
{
    /** @var array<string, Scope> Keyed with every contributing role's ScopeMapVersion — a compile() bump changes the key, i.e. a guaranteed miss. */
    private array $scopeMemo = [];

    private ?DateTimeImmutable $snapshotTime = null;

    /** @param  Closure(EntityActivity): bool  $isOwnerActivity */
    public function __construct(
        private readonly TenancyManager $tenancyManager,
        private readonly ScopeMapVersion $scopeMapVersion,
        private readonly ScopeAvailability $availability,
        private readonly DirectGrantLookup $directGrants,
        private readonly AuthOptions $options,
        private readonly ConnectionResolverInterface $connections,
        private readonly ClockInterface $clock,
        private readonly EntityActivity $activity,
        private readonly Closure $isOwnerActivity,
    ) {}

    /**
     * @template T
     *
     * @param  Closure(): T  $read
     * @return T|null
     */
    public function inSnapshot(ConnectionInterface $connection, Closure $read): mixed
    {
        if ($this->snapshotTime !== null || $this->connections->connection() !== $connection || ! ($this->isOwnerActivity)($this->activity)) {
            return null;
        }

        // Use one expiry time and bypass role scopes memoized before this transaction.
        $this->snapshotTime = $this->clock->now();

        try {
            return $read();
        } finally {
            $this->snapshotTime = null;
        }
    }

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

        return $this->directlyGranted(principal: $p, entity: $entityKey, action: $action);
    }

    public function scopeFor(Principal $p, string $entityKey, string $action, ?RecordContext $ctx = null): Scope
    {
        if (! $this->activity->isActive($entityKey)) {
            return Scope::None;
        }

        $roleIds = $this->activeRoleIds($p);

        if ($roleIds === []) {
            return Scope::None;
        }

        $memoKey = $this->snapshotTime === null
            ? $this->memoKey(p: $p, entityKey: $entityKey, action: $action, ctx: $ctx, roleIds: $roleIds)
            : null;

        if ($memoKey !== null && array_key_exists($memoKey, $this->scopeMemo)) {
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

        if ($memoKey !== null) {
            $this->scopeMemo[$memoKey] = $effective;
        }

        return $effective;
    }

    public function scope(Principal $principal, string $entity, string $action, ?RecordContext $context = null): Scope
    {
        return $this->availability->normalize(entityKey: $entity, scope: $this->scopeFor(p: $principal, entityKey: $entity, action: $action, ctx: $context));
    }

    public function directlyGranted(Principal $principal, string $entity, string $action): bool
    {
        return $this->directGrants->hasActive(p: $principal, key: $this->panel().'.'.$entity.'.'.$action,
            at: $this->snapshotTime, connection: $this->snapshotTime === null ? null : $this->connections->connection());
    }

    /** @return list<string> */
    private function activeRoleIds(Principal $p): array
    {
        $workspaceId = $this->tenancyManager->context()?->workspace?->id;

        $connection = $this->connections->connection();
        $now = ($this->snapshotTime ?? $this->clock->now())->format('Y-m-d H:i:s.uP');

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
