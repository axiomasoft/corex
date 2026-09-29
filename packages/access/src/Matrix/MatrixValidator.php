<?php

declare(strict_types=1);

namespace CoreX\Access\Matrix;

use CoreX\Access\Access\ScopeAvailability;
use CoreX\Access\Enums\PrincipalType;
use CoreX\Access\Enums\Scope;
use CoreX\Access\Models\Role;
use CoreX\Access\Models\RoleMatrixRow;
use CoreX\Modules\Contracts\EntityRegistry;
use CoreX\Modules\Enums\Capability;
use Illuminate\Support\Facades\Schema;

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
 *    {@see ScopeAvailability} would fail closed on at
 *    request time; this surfaces the same condition earlier, at
 *    matrix-authoring time.
 *
 * `$entities` defaults to null (resolved from the container lazily) so the
 * pre-existing zero-argument `new MatrixValidator` call sites (P2.8) keep
 * working unchanged; DI containers still inject the real EntityRegistry via
 * the constructor's type-hint.
 */
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
        private ?EntityRegistry $entities = null,
    ) {}

    /** @return list<string> */
    public function validate(Role $role): array
    {
        return [
            ...$this->degradedScopeWarnings($role),
            ...$this->capabilityDriftWarnings($role),
        ];
    }

    /** @return list<string> */
    private function degradedScopeWarnings(Role $role): array
    {
        $nonUserAssignable = array_values(array_diff($role->assignable_to, [PrincipalType::User->value]));

        if ($nonUserAssignable === []) {
            return [];
        }

        $hasDegradedScope = RoleMatrixRow::query()
            ->where('role_id', $role->id)
            ->whereIn('scope', self::OWNER_DEGRADED_SCOPES)
            ->exists();

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
    private function capabilityDriftWarnings(Role $role): array
    {
        $rows = RoleMatrixRow::query()
            ->where('role_id', $role->id)
            ->whereIn('scope', array_keys(self::SCOPE_CAPABILITY_REQUIREMENT))
            ->get(['entity_key', 'scope'])
            ->unique(fn (RoleMatrixRow $row): string => "{$row->entity_key}|{$row->scope->value}");

        $warnings = [];

        foreach ($rows as $row) {
            $warning = $this->capabilityDriftWarning($role, $row->entity_key, $row->scope->value);

            if ($warning !== null) {
                $warnings[] = $warning;
            }
        }

        return $warnings;
    }

    private function capabilityDriftWarning(Role $role, string $entityKey, string $scope): ?string
    {
        if (! $this->entities()->has($entityKey)) {
            return null;
        }

        $entity = $this->entities()->get($entityKey);
        $requirement = self::SCOPE_CAPABILITY_REQUIREMENT[$scope];

        if (in_array($requirement['capability'], $entity->capabilities, true)) {
            return null;
        }

        if (! Schema::hasColumn($entity->table, $requirement['column'])) {
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

    private function entities(): EntityRegistry
    {
        return $this->entities ?? app(EntityRegistry::class);
    }
}
