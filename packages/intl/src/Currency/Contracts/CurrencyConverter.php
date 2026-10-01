<?php

declare(strict_types=1);

namespace CoreX\Intl\Currency\Contracts;

use Brick\Money\Money;
use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use CoreX\Intl\Currency\Data\RateSnapshot;
use CoreX\Intl\Currency\Laravel\ConversionPolicy;
use CoreX\Intl\Currency\Laravel\ConversionReceipt;

#[Spi(kind: SpiKind::Implement, since: '1.0')]
interface CurrencyConverter
{
    public function convert(Money $amount, RateSnapshot $rate, ConversionPolicy $policy): ConversionReceipt;
}
