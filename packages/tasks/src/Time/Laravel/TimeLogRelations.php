<?php

declare(strict_types=1);

namespace CoreX\Tasks\Time\Laravel;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use CoreX\Tasks\Time\Internal\Models\TimeLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @corex-model-extension Exact TimeLog Eloquent relationship composition.
 * The calling model supplies foreign/local keys; related model stays owner-private.
 */
#[Spi(kind: SpiKind::Call, since: '1.0')]
final class TimeLogRelations
{
    /** @template TModel of Model
     * @param  TModel  $model
     * @return HasMany<TimeLog, TModel>
     */
    public static function hasMany(Model $model, ?string $foreignKey = null, ?string $localKey = null): HasMany
    {
        $relationResult = $model->hasMany(TimeLog::class, $foreignKey, $localKey);

        return $relationResult;
    }
}
