<?php

declare(strict_types=1);

namespace CoreX\Access\Roles\Internal\Models;

use CoreX\Context\Enums\PrincipalType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Packman\Attributes\Internal;

/**
 * `aut_role_assignments` — a role granted to a principal (user, agent or API key), optionally scoped to one workspace (`null` = whole account) and
 * optionally time-limited (`expires_at`, TTL). No soft deletes — the table
 * carries no `deleted_at`. workspace_id/granted_by carry no FK here — the
 * cross-package cloud migration adds both.
 *
 * @property string $id
 * @property string $role_id
 * @property PrincipalType $principal_type
 * @property string $principal_id
 * @property ?string $workspace_id
 * @property ?string $granted_by
 * @property ?Carbon $expires_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
#[Internal]
final class RoleAssignment extends Model
{
    use HasUuids;

    protected $table = 'aut_role_assignments';

    protected $dateFormat = 'Y-m-d H:i:s.uP';

    /** @var list<string> */
    protected $fillable = [
        'role_id',
        'principal_type',
        'principal_id',
        'workspace_id',
        'granted_by',
        'expires_at',
    ];

    /** @return BelongsTo<Role, $this> */
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'principal_type' => PrincipalType::class,
            'expires_at' => 'datetime',
        ];
    }
}
