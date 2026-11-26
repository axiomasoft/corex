<?php

declare(strict_types=1);

namespace CoreX\Audit\Internal;

use CoreX\Audit\Enums\ActorType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Packman\Attributes\Internal;

/**
 * Append-only audit entry (sys_audit_log). No updated_at:
 * An append-only class
 * keeps updated_at only if it has ≥1 legitimate post-insert field; this
 * table has none.
 *
 * @property string $id
 * @property Carbon $occurred_at
 * @property ActorType $actor_type
 * @property string|null $actor_id
 * @property string|null $workspace_id
 * @property string|null $entity_type
 * @property string|null $entity_id
 * @property string $action
 * @property array<string, mixed>|null $changes
 * @property array<string, mixed> $context
 *
 * @internal
 */
#[Internal]
final class AuditLog extends Model
{
    use HasUuids;

    public const UPDATED_AT = null;

    protected $table = 'sys_audit_log';

    protected $guarded = [];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'occurred_at' => 'immutable_datetime',
            'actor_type' => ActorType::class,
            'changes' => 'array',
            'context' => 'array',
        ];
    }
}
