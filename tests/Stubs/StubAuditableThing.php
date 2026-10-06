<?php

declare(strict_types=1);

namespace CoreX\Tests\Stubs;

use CoreX\Audit\Contracts\Auditable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * EntityRegistry-driven model (AuditLogTest AC-20) — deliberately does NOT
 * use HasAuditLog, exercising the auto-observer path wired by
 * CoreX\Modules\Internal\Audit\AuditCapabilityWiring instead of the manual trait. Uuid
 * key (HasUuids) because entity_id in sys_audit_log is a uuid column.
 */
final class StubAuditableThing extends Model implements Auditable
{
    use HasUuids;

    protected $table = 'stub_auditable_things';

    protected $guarded = [];

    public function auditExcept(): array
    {
        return ['created_at', 'updated_at'];
    }

    public function auditSensitive(): array
    {
        return ['secret'];
    }
}
