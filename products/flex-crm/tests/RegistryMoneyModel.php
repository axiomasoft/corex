<?php

declare(strict_types=1);

namespace Flex\Crm\Tests;

use CoreX\Database\Laravel\BaseModel;
use CoreX\Intl\Money\Laravel\MoneyCast;

final class RegistryMoneyModel extends BaseModel
{
    protected $casts = ['amount' => MoneyCast::class.':USD'];
}
