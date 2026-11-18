<?php

declare(strict_types=1);

namespace CoreX\Access\Matrix\Internal;

use CoreX\Access\Enums\Scope;
use CoreX\Access\Roles\Data\RoleMatrixEntry;
use CoreX\Access\Roles\Data\RoleView;
use CoreX\Access\Roles\Laravel\RoleMatrixData;
use CoreX\Context\Enums\PrincipalType;
use Illuminate\Database\Connection;
use Illuminate\Database\ConnectionResolverInterface;
use Illuminate\Database\Schema\Builder as SchemaBuilder;
use Packman\Attributes\Internal;
use Packman\Modules\Contracts\Entities\EntityRegistry;
use Packman\Modules\Enums\Entities\Capability;

/**
 * Warns (never throws) about two classes of matrix-row risk — the return
 * value is the whole contract, never a log:
 *
 * 1. A role assignable to agent/api_key carries own/dept/dept_tree rows:
 *    those scopes degrade to `none` for non-user principals (B-11 §5.5) — a
 *    role designed for agents should use workspace/account instead.
 * 2. (P2.9, D114) A row names own/dept/dept_tree/workspace on an entity
 *    whose table HAS the matching column (`owner_id`/`workspace_id`) but
 *    whose `EntityDefinition` never declared the matching capability
 *    (`Ownable`/`WorkspaceScoped`) — declaration drift that
 *    {@see CoreX\Access\Access\Internal\ScopeAvailability} would fail closed on at
 *    request time; this surfaces the same condition earlier, at
 *    matrix-authoring time.
 */
#[Internal]
final readonly class MatrixValidator
{
    private const array OWNER_DEGRADED_SCOPES = [
        Scope::Own->value,
        Scope::Dept->value,
        Scope::DeptTree->value,
    ];

    /** @var array<string, array{capability: Capability, column: string}> */
    private const array SCOPE_CAPABILITY_REQUIREMENT = [
        'own' => ['capability' => Capability::Ownable, 'column' => 'owner_id'],
        'dept' => ['capability' => Capability::Ownable, 'column' => 'owner_id'],
        'dept_tree' => ['capability' => Capability::Ownable, 'column' => 'owner_id'],
        'workspace' => ['capability' => Capability::WorkspaceScoped, 'column' => 'workspace_id'],
    ];

    public function __construct(
        private EntityRegistry $entities,
        private ConnectionResolverInterface $connections,
    ) {}

    /** @return list<string> */
    public function validate(RoleView $role): array
    {
        return [
            ...$this->degradedScopeWarnings($role),
            ...$this->capabilityDriftWarnings($role),
        ];
    }

    /** @return list<string> */
    private function degradedScopeWarnings(RoleView $role): array
    {
        $nonUserAssignable = array_values(array_diff($role->assignableTo, [PrincipalType::User->value]));

        if ($nonUserAssignable === []) {
            return [];
        }

        $hasDegradedScope = array_any(RoleMatrixData::matrix($role->id), static fn (RoleMatrixEntry $row): bool => in_array($row->scope->value, self::OWNER_DEGRADED_SCOPES, true));

        if (! $hasDegradedScope) {
            return [];
        }

        return [sprintf(
            "Role '%s' is assignable to %s, but its matrix uses own/dept/dept_tree scope — these degrade to none for non-user principals.",
            $role->slug,
            implode(', ', $nonUserAssignable),
        )];
    }

    /** @return list<string> */
    private function capabilityDriftWarnings(RoleView $role): array
    {
        $rows = collect(RoleMatrixData::matrix($role->id))
            ->filter(static fn (RoleMatrixEntry $row): bool => isset(self::SCOPE_CAPABILITY_REQUIREMENT[$row->scope->value]))
            ->unique(fn (RoleMatrixEntry $row): string => "{$row->entityKey}|{$row->scope->value}");

        $warnings = [];

        foreach ($rows as $row) {
            $warning = $this->capabilityDriftWarning(role: $role, entityKey: $row->entityKey, scope: $row->scope->value);

            if ($warning !== null) {
                $warnings[] = $warning;
            }
        }

        return $warnings;
    }

    private function capabilityDriftWarning(RoleView $role, string $entityKey, string $scope): ?string
    {
        if (! $this->entities->has($entityKey)) {
            return null;
        }

        $entity = $this->entities->get($entityKey);
        $requirement = self::SCOPE_CAPABILITY_REQUIREMENT[$scope];

        if (in_array($requirement['capability'], $entity->capabilities, true)) {
            return null;
        }

        if (! $this->schema()->hasColumn($entity->table, $requirement['column'])) {
            return null;
        }

        return sprintf(
            "Role '%s' matrix has entity '%s' with scope '%s', which requires capability '%s' — the entity's table has column '%s' but its EntityDefinition does not declare that capability.",
            $role->slug,
            $entityKey,
            $scope,
            $requirement['capability']->value,
            $requirement['column'],
        );
    }

    private function schema(): SchemaBuilder
    {
        /** @var Connection */
        $connection = $this->connections->connection();

        return $connection->getSchemaBuilder();
    }
}
