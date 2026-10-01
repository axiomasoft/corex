<?php

declare(strict_types=1);

namespace Flex\Crm\Deal\Casts;

use Brick\Money\Money;
use CoreX\Intl\Money\Laravel\MoneyCast;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/** @implements CastsAttributes<Money|null, mixed> */
final class DealAmount implements CastsAttributes
{
    /** @param array<string, mixed> $attributes */
    public function get(Model $model, string $key, mixed $value, array $attributes): ?Money
    {
        return (new MoneyCast('amount', 'currency'))->get($model, $key, $value, $attributes);
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, int|string|null>
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): array
    {
        // Unlike the generic nullable pair, CRM currency remains mandatory.
        return $value === null
            ? ['amount' => null, 'currency' => $attributes['currency'] ?? 'RUB']
            : (new MoneyCast('amount', 'currency'))->set($model, $key, $value, $attributes);
    }
}
