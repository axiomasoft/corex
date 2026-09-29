<?php

declare(strict_types=1);

namespace CoreX\Intl\Currency\Contracts;

use Brick\Money\Money;
use CoreX\Intl\Currency\Data\ConversionPolicy;
use CoreX\Intl\Currency\Data\ConversionReceipt;
use CoreX\Intl\Currency\Data\RateSnapshot;

interface CurrencyConverter
{
    public function convert(Money $amount, RateSnapshot $rate, ConversionPolicy $policy): ConversionReceipt;
}
