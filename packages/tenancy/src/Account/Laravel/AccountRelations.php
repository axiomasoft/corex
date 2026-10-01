<?php

declare(strict_types=1);

namespace CoreX\Tenancy\Account\Laravel;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use CoreX\Tenancy\Account\Internal\Models\Account;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @corex-model-extension Exact Account Eloquent relationship composition.
 * The calling model supplies foreign/local keys; related model stays owner-private.
 */
#[Spi(kind: SpiKind::Call, since: '1.0')]
final class AccountRelations
{
    /** @template TModel of Model
     * @param  TModel  $model
     * @return BelongsTo<Account, TModel>
     */
    public static function belongsTo(Model $model, ?string $foreignKey = null, ?string $ownerKey = null, ?string $relation = null): BelongsTo
    {
        $relationResult = $model->belongsTo(Account::class, $foreignKey, $ownerKey, $relation);

        return $relationResult;
    }
}
