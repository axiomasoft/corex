<?php

declare(strict_types=1);

namespace CoreX\Intl\Contracts;

use Brick\Money\Money;
use CoreX\Intl\Data\ConversionPolicy;
use CoreX\Intl\Data\ConversionReceipt;
use CoreX\Intl\Data\RateSnapshot;

interface CurrencyConverter
{
    public function convert(Money $amount, RateSnapshot $rate, ConversionPolicy $policy): ConversionReceipt;
}
