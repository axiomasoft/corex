<?php

declare(strict_types=1);

namespace CoreX\Intl\Formatting\Contracts;

use Brick\Money\Money;
use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use CoreX\Intl\Formatting\Data\FormatPolicy;
use CoreX\Intl\Formatting\Data\FormattedMoney;
use CoreX\Intl\Locale\Data\LocaleResolution;

#[Spi(kind: SpiKind::Implement, since: '1.0')]
interface MoneyFormatter
{
    public function format(Money $amount, LocaleResolution $locale, FormatPolicy $policy): FormattedMoney;
}
