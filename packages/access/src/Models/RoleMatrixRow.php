<?php

declare(strict_types=1);

namespace CoreX\Access\Models;

use CoreX\Access\Enums\Scope;
use CoreX\Access\Roles\SystemRoleImmutable;
use CoreX\Database\HasConfiguredId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Override;

/**
 * `aut_role_matrix` — one row of role × entity × action × scope[, context]
 * (B-11 §2.3/§5.5). No soft deletes — the table carries no `deleted_at`.
 * Immutable for a system role's matrix outside the seeder/module-merge path
 * (AC-21) — a system role ships with an empty matrix; modules populate it
 * via `Extend\Permissions` (B-11 §4 п.2).
 *
 * @property string $id
 * @property string $role_id
 * @property string $entity_key
 * @property string $action
 * @property Scope $scope
 * @property ?string $context_type
 * @property ?string $context_id
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
final class RoleMatrixRow extends Model
{
    use HasConfiguredId;
    use SystemRoleImmutable;

    protected $table = 'aut_role_matrix';

    /** @var list<string> */
    protected $fillable = [
        'role_id',
        'entity_key',
        'action',
        'scope',
        'context_type',
        'context_id',
    ];

    /** @return BelongsTo<Role, $this> */
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    #[Override]
    protected function affectsSystemRole(): bool
    {
        return Role::withTrashed()->whereIn('id', array_filter([$this->role_id, $this->getRawOriginal('role_id')]))
            ->where('is_system', true)->exists();
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'scope' => Scope::class,
        ];
    }
}
