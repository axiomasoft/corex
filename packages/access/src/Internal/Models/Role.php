<?php

declare(strict_types=1);

namespace CoreX\Access\Internal\Models;

use CoreX\Access\Roles\Internal\SystemRoleImmutable;
use CoreX\Database\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Override;

/**
 * `aut_roles` — role overlay compiled into AzGuard (B-11 §2.3).
 *
 * @property string $id
 * @property string $slug
 * @property string $name
 * @property ?string $description
 * @property bool $is_system
 * @property ?string $cloned_from_id
 * @property ?int $azguard_role_id
 * @property list<string> $assignable_to
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property ?Carbon $deleted_at
 */
final class Role extends BaseModel
{
    use SystemRoleImmutable;

    protected $table = 'aut_roles';

    /** @var list<string> */
    protected $fillable = [
        'slug',
        'name',
        'description',
        'is_system',
        'cloned_from_id',
        'azguard_role_id',
        'assignable_to',
    ];

    /** @return BelongsTo<Role, $this> */
    public function clonedFrom(): BelongsTo
    {
        return $this->belongsTo(self::class, 'cloned_from_id');
    }

    /** @return HasMany<RoleMatrixRow, $this> */
    public function matrix(): HasMany
    {
        return $this->hasMany(RoleMatrixRow::class);
    }

    /** @return HasMany<RoleAssignment, $this> */
    public function assignments(): HasMany
    {
        return $this->hasMany(RoleAssignment::class);
    }

    #[Override]
    protected function affectsSystemRole(): bool
    {
        return (bool) $this->is_system || (bool) $this->getRawOriginal('is_system');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'is_system' => 'boolean',
            'assignable_to' => 'array',
        ];
    }
}
