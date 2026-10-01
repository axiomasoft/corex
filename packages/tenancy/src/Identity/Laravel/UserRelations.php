<?php

declare(strict_types=1);

namespace CoreX\Tenancy\Identity\Laravel;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use CoreX\Tenancy\Identity\Internal\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @corex-model-extension Exact User Eloquent relationship composition.
 * The calling model supplies foreign/local keys; related model stays owner-private.
 */
#[Spi(kind: SpiKind::Call, since: '1.0')]
final class UserRelations
{
    /** @template TModel of Model
     * @param  TModel  $model
     * @return BelongsTo<User, TModel>
     */
    public static function belongsTo(Model $model, ?string $foreignKey = null, ?string $ownerKey = null, ?string $relation = null): BelongsTo
    {
        $relationResult = $model->belongsTo(User::class, $foreignKey, $ownerKey, $relation);

        return $relationResult;
    }
}
