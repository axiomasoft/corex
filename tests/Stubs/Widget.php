<?php

declare(strict_types=1);

namespace CoreX\Tests\Stubs;

use Brick\Money\Money;
use CoreX\Contracts\Auditable;
use CoreX\Models\TenantModel;
use CoreX\Money\MoneyCast;

/**
 * Test double exercising the kernel: ULID + soft deletes + audit (BaseModel),
 * tenant scope (TenantModel), and a money cast.
 *
 * @property string $id
 * @property string|null $tenant_id
 * @property string $name
 * @property Money|null $price
 */
final class Widget extends TenantModel implements Auditable
{
    protected $guarded = [];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'price' => MoneyCast::class.':amount_minor,currency',
        ];
    }
}
