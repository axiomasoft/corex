<?php

declare(strict_types=1);

namespace CoreX\Access\Roles\Internal;

use CoreX\Access\Contracts\RoleMatrixCompiler;
use CoreX\Access\Data\RecordContext;
use CoreX\Access\Enums\Scope;
use CoreX\Access\Roles\Internal\Models\Role;
use CoreX\Access\Roles\Internal\Models\RoleAssignment;
use CoreX\Access\Roles\Internal\Models\RoleMatrixRow;
use CoreX\Access\Roles\RoleManager;
use CoreX\Context\Contracts\Principal;
use CoreX\Context\Enums\PrincipalType;
use DateTimeImmutable;
use DomainException;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\ConnectionResolverInterface;
use InvalidArgumentException;
use LogicException;
use Packman\Attributes\Internal;

/** Manages roles in the current tenant database. */
#[Internal]
final readonly class DatabaseRoleManager implements RoleManager
{
    public function __construct(
        private ConnectionResolverInterface $connections,
        private RoleMatrixCompiler $compiler,
    ) {}

    /** @param list<PrincipalType> $assignableTo */
    public function create(string $slug, string $name, array $assignableTo = [PrincipalType::User]): string
    {
        if (trim($slug) === '' || trim($name) === '' || $assignableTo === []) {
            throw new InvalidArgumentException('A role needs a slug, a name and at least one principal type.');
        }

        $types = array_unique(array_map(static fn (PrincipalType $type): string => $type->value, $assignableTo));
        sort($types);

        $this->connection();
        $role = Role::query()->firstOrCreate(['slug' => $slug], ['name' => $name, 'assignable_to' => $types]);
        $storedTypes = $role->assignable_to;
        sort($storedTypes);

        if ($role->is_system || $role->name !== $name || $storedTypes !== $types) {
            throw new DomainException('The role slug is already in use by a different role definition.');
        }

        return $role->id;
    }

    public function setScope(string $roleId, string $entityKey, string $action, Scope $scope, ?RecordContext $context = null): void
    {
        $this->connection()->transaction(function () use ($roleId, $entityKey, $action, $scope, $context): void {
            $role = $this->lockedRole($roleId);
            $row = RoleMatrixRow::query()->firstOrNew([
                'role_id' => $role->id,
                'entity_key' => $entityKey,
                'action' => $action,
                'context_type' => $context?->type,
                'context_id' => $context?->id,
            ]);

            if ($row->exists && $row->scope === $scope) {
                return;
            }

            $row->scope = $scope;
            $row->save();
            $this->compiler->compile($role->id);
        });
    }

    public function assign(string $roleId, Principal $principal, ?string $workspaceId = null, ?DateTimeImmutable $expiresAt = null): void
    {
        $this->connection()->transaction(function () use ($roleId, $principal, $workspaceId, $expiresAt): void {
            $role = $this->lockedRole($roleId);

            if (! in_array($principal->principalType()->value, $role->assignable_to, true)) {
                throw new DomainException('This role cannot be assigned to the supplied principal type.');
            }

            RoleAssignment::query()->updateOrCreate([
                'role_id' => $role->id,
                'principal_type' => $principal->principalType()->value,
                'principal_id' => $principal->principalId(),
                'workspace_id' => $workspaceId,
            ], ['expires_at' => $expiresAt]);
        });
    }

    public function unassign(string $roleId, Principal $principal, ?string $workspaceId = null): void
    {
        $this->connection()->transaction(function () use ($roleId, $principal, $workspaceId): void {
            $role = $this->lockedRole($roleId);
            $role->assignments()->toBase()
                ->where('principal_type', $principal->principalType()->value)
                ->where('principal_id', $principal->principalId())
                ->where('workspace_id', $workspaceId)
                ->delete();
        });
    }

    private function lockedRole(string $roleId): Role
    {
        $query = Role::query();
        $query->getQuery()->lockForUpdate();

        return $query->findOrFail($roleId);
    }

    private function connection(): ConnectionInterface
    {
        if (Role::getConnectionResolver() !== $this->connections) {
            throw new LogicException('Role management requires the owning application to be active for Eloquent.');
        }

        return $this->connections->connection();
    }
}
