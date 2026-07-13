<?php

declare(strict_types=1);

namespace CoreX\Audit;

use CoreX\Contracts\Auditable;
use CoreX\Contracts\AuditEntry;
use CoreX\Contracts\AuditLogger;
use Illuminate\Database\Eloquent\Model;

/**
 * Single write path for both audit sources (B-10 §4.4 capability +
 * HasAuditLog trait) — one diff algorithm, one AuditLogger::record() call
 * per change, so a model wired to both never double-records.
 *
 * Eloquent-observer instance (created/updated/deleted) for models exposed
 * via EntityRegistry::byCapability(Auditable) — attached in
 * Flex\Modules\ModulesServiceProvider::boot(), not here: this package
 * cannot depend on flex/modules (D22 — flex/modules already requires
 * corex/core, a reverse dependency would cycle).
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

    public static function record(Model $model, string $action): void
    {
        $except = array_flip(self::exceptFor($model));
        $sensitive = array_flip(self::sensitiveFor($model));
        $changes = [];

        if ($action === 'created') {
            /** @var array<string, mixed> $attributes */
            $attributes = $model->getAttributes();

            foreach ($attributes as $attribute => $value) {
                if (isset($except[$attribute])) {
                    continue;
                }

                $changes[$attribute] = ['old' => null, 'new' => isset($sensitive[$attribute]) ? '••••••' : $value];
            }
        } else {
            foreach ($model->getChanges() as $attribute => $value) {
                if (isset($except[$attribute])) {
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
