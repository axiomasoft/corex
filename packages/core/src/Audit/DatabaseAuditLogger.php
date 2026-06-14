<?php

declare(strict_types=1);

namespace CoreX\Audit;

use CoreX\Contracts\Auditable;
use CoreX\Contracts\AuditLogger;
use CoreX\Models\AuditLog;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;

final class DatabaseAuditLogger implements AuditLogger
{
    public function log(Auditable $model, string $event, array $changes = [], ?Authenticatable $actor = null): void
    {
        $entry = new AuditLog;
        $entry->auditable_type = $this->morphType($model);
        $entry->auditable_id = $model instanceof Model ? (string) $model->getKey() : '';
        $entry->event = $event;
        $entry->changes = $changes;
        $entry->actor_type = $actor !== null ? $actor::class : null;

        $identifier = $actor?->getAuthIdentifier();
        $entry->actor_id = $identifier !== null ? (string) $identifier : null;

        $entry->save();
    }

    private function morphType(Auditable $model): string
    {
        return $model instanceof Model ? $model->getMorphClass() : $model::class;
    }
}
