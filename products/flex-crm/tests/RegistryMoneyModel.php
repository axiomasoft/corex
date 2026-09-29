<?php

declare(strict_types=1);

namespace Flex\Crm\Tests;

use CoreX\Database\BaseModel;
use CoreX\Intl\Money\MoneyCast;

final class RegistryMoneyModel extends BaseModel
{
    protected $casts = ['amount' => MoneyCast::class.':USD'];
}
