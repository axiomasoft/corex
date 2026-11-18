<?php

declare(strict_types=1);

namespace CoreX\Tasks\Sprint\Laravel;

use CoreX\Tasks\Sprint\Internal\Models\Sprint;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Packman\Attributes\Spi;
use Packman\Attributes\SpiKind;

/**
 * @corex-model-extension Exact Sprint Eloquent relationship composition.
 * The calling model supplies foreign/local keys; related model stays owner-private.
 */
#[Spi(kind: SpiKind::Call, since: '1.0')]
final class SprintRelations
{
    /** @template TModel of Model
     * @param  TModel  $model
     * @return HasMany<covariant Model, TModel>
     */
    public static function hasMany(Model $model, ?string $foreignKey = null, ?string $localKey = null): HasMany
    {
        $relationResult = $model->hasMany(Sprint::class, $foreignKey, $localKey);

        return $relationResult;
    }

    /** @template TModel of Model
     * @param  TModel  $model
     * @return BelongsTo<covariant Model, TModel>
     */
    public static function belongsTo(Model $model, ?string $foreignKey = null, ?string $ownerKey = null, ?string $relation = null): BelongsTo
    {
        $relationResult = $model->belongsTo(Sprint::class, $foreignKey, $ownerKey, $relation);

        return $relationResult;
    }
}
