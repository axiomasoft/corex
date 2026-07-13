<?php

declare(strict_types=1);

namespace CoreX\Concerns;

use CoreX\Audit\AuditObserver;
use CoreX\Contracts\Auditable;
use CoreX\Support\Config;
use Illuminate\Database\Eloquent\Model;

/**
 * Records an append-only change history via AuditObserver::record() (single
 * write path shared with the EntityRegistry-driven auto-observer, see
 * AuditObserver). Manual opt-in for models outside EntityRegistry (e.g. a
 * host app's User model); the model must also implement Auditable.
 *
 * @mixin Model
 */
trait HasAuditLog
{
    public static function bootHasAuditLog(): void
    {
        if (! Config::auditEnabled()) {
            return;
        }

        static::created(static fn (Model $model): null => self::recordAudit($model, 'created'));
        static::updated(static fn (Model $model): null => self::recordAudit($model, 'updated'));
        static::deleted(static fn (Model $model): null => self::recordAudit($model, 'deleted'));
    }

    /**
     * Attributes excluded from the audit diff.
     *
     * @return list<string>
     */
    public function auditExcept(): array
    {
        return ['created_at', 'updated_at'];
    }

    /**
     * Attributes masked in the audit diff.
     *
     * @return list<string>
     */
    public function auditSensitive(): array
    {
        return [];
    }

    private static function recordAudit(Model $model, string $action): null
    {
        if ($model instanceof Auditable) {
            AuditObserver::record($model, $action);
        }

        return null;
    }
}
