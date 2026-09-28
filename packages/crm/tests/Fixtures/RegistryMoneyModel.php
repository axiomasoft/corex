<?php

declare(strict_types=1);

namespace Flex\Crm\Tests\Fixtures;

use CoreX\Models\BaseModel;
use CoreX\Money\MoneyCast;

final class RegistryMoneyModel extends BaseModel
{
    protected $casts = ['amount' => MoneyCast::class.':USD'];
}
