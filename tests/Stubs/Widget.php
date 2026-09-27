<?php

declare(strict_types=1);

namespace CoreX\Tests\Stubs;

use Brick\Money\Money;
use CoreX\Audit\Contracts\Auditable;
use CoreX\Models\BaseModel;
use CoreX\Money\MoneyCast;

/**
 * Test double exercising the kernel: ULID + soft deletes + audit (BaseModel)
 * and a money cast.
 *
 * @property string $id
 * @property string $name
 * @property Money|null $price
 */
final class Widget extends BaseModel implements Auditable
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
