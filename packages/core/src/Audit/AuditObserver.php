<?php

declare(strict_types=1);

namespace CoreX\Audit;

use CoreX\Contracts\Auditable;
use CoreX\Contracts\AuditEntry;
use CoreX\Contracts\AuditLogger;
use CoreX\Support\Config;
use Illuminate\Database\Eloquent\Model;

/**
 * Single write path for both audit sources (B-10 §4.4 capability +
 * HasAuditLog trait) — one diff algorithm, one AuditLogger::record() call
 * per change, so a model wired to both never double-records.
 *
 * Eloquent-observer instance (created/updated/deleted/restored/forceDeleted)
 * for models exposed via EntityRegistry::byCapability(Auditable) — attached in
 * Flex\Modules\ModulesServiceProvider::boot(), not here: this package cannot
 * depend on flex/modules (D22 — flex/modules already requires corex/core, a
 * reverse dependency would cycle).
 */
final class AuditObserver
{
    public function created(Model $model): void
    {
        self::record($model, 'created');
    }

    public function updated(Model $model): void
    {
        self::record($model, 'updated');
    }

    public function deleted(Model $model): void
    {
        self::record($model, 'deleted');
    }

    public function restored(Model $model): void
    {
        self::record($model, 'restored');
    }

    public function forceDeleted(Model $model): void
    {
        self::record($model, 'forceDeleted');
    }

    public static function record(Model $model, string $action): void
    {
        // Killswitch is checked HERE (runtime, single write path), not in
        // HasAuditLog::boot (A60): a boot-time skip never re-enables when the
        // config flips, and the capability path (::observe) never saw the flag
        // at all — a disabled audit still wrote through byCapability(Auditable).
        if (! Config::auditEnabled()) {
            return;
        }

        // forceDelete() fires BOTH 'deleted' and 'forceDeleted' (Eloquent
        // SoftDeletes) — record the permanent removal once, as forceDeleted.
        if ($action === 'deleted' && self::isForceDeleting($model)) {
            return;
        }

        // Diff scope is the model's mass-assignable set (A17): guarded
        // attributes (password, remember_token, …) never enter `changes` — they
        // are OMITTED, not masked (B-10 §7.4 п.8: a masked key still leaks the
        // field's name and that it changed). Fillable-but-sensitive fields are
        // masked via auditSensitive() instead.
        $except = array_flip(self::exceptFor($model));
        $sensitive = array_flip(self::sensitiveFor($model));
        $changes = [];

        if ($action === 'created') {
            /** @var array<string, mixed> $attributes */
            $attributes = $model->getAttributes();

            foreach ($attributes as $attribute => $value) {
                if (isset($except[$attribute]) || ! $model->isFillable((string) $attribute)) {
                    continue;
                }

                $changes[$attribute] = ['old' => null, 'new' => isset($sensitive[$attribute]) ? '••••••' : $value];
            }
        } else {
            foreach ($model->getChanges() as $attribute => $value) {
                if (isset($except[$attribute]) || ! $model->isFillable((string) $attribute)) {
                    continue;
                }

                $old = $model->getOriginal($attribute);

                if (isset($sensitive[$attribute])) {
                    $old = $old !== null ? '••••••' : null;
                    $value = '••••••';
                }

                $changes[$attribute] = ['old' => $old, 'new' => $value];
            }
        }

        app(AuditLogger::class)->record(new AuditEntry(
            action: $action,
            actorType: CurrentActor::type(),
            actorId: CurrentActor::id(),
            entityType: $model->getMorphClass(),
            entityId: (string) $model->getKey(),
            changes: $changes === [] ? null : $changes,
            context: CurrentActor::context(),
        ));
    }

    private static function isForceDeleting(Model $model): bool
    {
        return method_exists($model, 'isForceDeleting') && $model->isForceDeleting();
    }

    /** @return list<string> */
    private static function exceptFor(Model $model): array
    {
        return $model instanceof Auditable ? $model->auditExcept() : ['created_at', 'updated_at'];
    }

    /** @return list<string> */
    private static function sensitiveFor(Model $model): array
    {
        return $model instanceof Auditable ? $model->auditSensitive() : [];
    }
}
