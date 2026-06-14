<?php

declare(strict_types=1);

namespace CoreX\Concerns;

use CoreX\Contracts\Auditable;
use CoreX\Contracts\AuditLogger;
use CoreX\Support\Config;
use Illuminate\Database\Eloquent\Model;

/**
 * Records an append-only change history via the AuditLogger. The model must
 * also implement the Auditable contract.
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

    private static function recordAudit(Model $model, string $event): null
    {
        if (! $model instanceof Auditable) {
            return null;
        }

        $except = array_flip($model->auditExcept());
        $changes = [];

        if ($event === 'created') {
            /** @var array<string, mixed> $attributes */
            $attributes = $model->getAttributes();

            foreach ($attributes as $attribute => $value) {
                if (isset($except[$attribute])) {
                    continue;
                }

                $changes[$attribute] = ['old' => null, 'new' => $value];
            }
        } else {
            foreach ($model->getChanges() as $attribute => $value) {
                if (isset($except[$attribute])) {
                    continue;
                }

                $changes[$attribute] = ['old' => $model->getOriginal($attribute), 'new' => $value];
            }
        }

        app(AuditLogger::class)->log($model, $event, $changes, auth()->user());

        return null;
    }
}
