<?php

declare(strict_types=1);

namespace CoreX\Tenancy\DomainTrust\Laravel;

use CoreX\Tenancy\DomainTrust\Internal\Models\Domain;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Packman\Attributes\Spi;
use Packman\Attributes\SpiKind;

/**
 * @corex-model-extension Exact Domain Eloquent relationship composition.
 * The calling model supplies foreign/local keys; related model stays owner-private.
 */
#[Spi(kind: SpiKind::Call, since: '1.0')]
final class DomainRelations
{
    /** @template TModel of Model
     * @param  TModel  $model
     * @return HasMany<covariant Model, TModel>
     */
    public static function hasMany(Model $model, ?string $foreignKey = null, ?string $localKey = null): HasMany
    {
        $relationResult = $model->hasMany(Domain::class, $foreignKey, $localKey);

        return $relationResult;
    }
}
