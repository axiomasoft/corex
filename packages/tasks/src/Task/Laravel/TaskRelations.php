<?php

declare(strict_types=1);

namespace CoreX\Tasks\Task\Laravel;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use CoreX\Tasks\Task\Internal\Models\Task;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @corex-model-extension Exact Task Eloquent relationship composition.
 * The calling model supplies foreign/local keys; related model stays owner-private.
 */
#[Spi(kind: SpiKind::Call, since: '1.0')]
final class TaskRelations
{
    /** @template TModel of Model
     * @param  TModel  $model
     * @return HasMany<Task, TModel>
     */
    public static function hasMany(Model $model, ?string $foreignKey = null, ?string $localKey = null): HasMany
    {
        $relationResult = $model->hasMany(Task::class, $foreignKey, $localKey);

        return $relationResult;
    }

    /** @template TModel of Model
     * @param  TModel  $model
     * @return BelongsTo<Task, TModel>
     */
    public static function belongsTo(Model $model, ?string $foreignKey = null, ?string $ownerKey = null, ?string $relation = null): BelongsTo
    {
        $relationResult = $model->belongsTo(Task::class, $foreignKey, $ownerKey, $relation);

        return $relationResult;
    }
}
