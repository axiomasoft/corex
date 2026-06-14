<?php

declare(strict_types=1);

namespace CoreX\Models;

use CoreX\Concerns\HasUlidKey;
use CoreX\Support\Config;
use Illuminate\Database\Eloquent\Model;
use Override;

/**
 * Append-only audit entry.
 *
 * @property string $auditable_type
 * @property string $auditable_id
 * @property string $event
 * @property array<string, mixed> $changes
 * @property string|null $actor_type
 * @property string|null $actor_id
 */
final class AuditLog extends Model
{
    use HasUlidKey;

    public const UPDATED_AT = null;

    protected $guarded = [];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['changes' => 'array'];
    }

    #[Override]
    public function getTable(): string
    {
        return Config::auditLogsTable();
    }
}
