<?php

declare(strict_types=1);

namespace CoreX\Tenancy\Provisioning\Laravel;

use CoreX\Tenancy\Provisioning\Internal\Models\MigrationBatch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Packman\Attributes\Spi;
use Packman\Attributes\SpiKind;

/**
 * @corex-model-extension Exact MigrationBatch Eloquent relationship composition.
 * The calling model supplies foreign/local keys; related model stays owner-private.
 */
#[Spi(kind: SpiKind::Call, since: '1.0')]
final class MigrationBatchRelations
{
    /** @template TModel of Model
     * @param  TModel  $model
     * @return BelongsTo<covariant Model, TModel>
     */
    public static function belongsTo(Model $model, ?string $foreignKey = null, ?string $ownerKey = null, ?string $relation = null): BelongsTo
    {
        $relationResult = $model->belongsTo(MigrationBatch::class, $foreignKey, $ownerKey, $relation);

        return $relationResult;
    }
}
