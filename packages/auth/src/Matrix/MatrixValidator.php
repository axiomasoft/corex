<?php

declare(strict_types=1);

namespace CoreX\Auth\Matrix;

use CoreX\Auth\Enums\PrincipalType;
use CoreX\Auth\Enums\Scope;
use CoreX\Auth\Models\Role;
use CoreX\Auth\Models\RoleMatrixRow;

/**
 * Warns (never throws) when a role assignable to agent/api_key carries
 * own/dept/dept_tree matrix rows: those scopes degrade to `none` for
 * non-user principals (B-11 §5.5) — a role designed for agents should use
 * workspace/account instead. Read the returned list; this is not a log.
 */
final readonly class MatrixValidator
{
    private const array OWNER_DEGRADED_SCOPES = [
        Scope::Own->value,
        Scope::Dept->value,
        Scope::DeptTree->value,
    ];

    /** @return list<string> */
    public function validate(Role $role): array
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
}
