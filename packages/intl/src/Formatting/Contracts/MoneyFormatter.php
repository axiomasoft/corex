<?php

declare(strict_types=1);

namespace CoreX\Intl\Formatting\Contracts;

use Brick\Money\Money;
use CoreX\Intl\Formatting\Data\FormatPolicy;
use CoreX\Intl\Formatting\Data\FormattedMoney;
use CoreX\Intl\Locale\Data\LocaleResolution;
use Packman\Attributes\Spi;
use Packman\Attributes\SpiKind;

#[Spi(kind: SpiKind::Implement, since: '1.0')]
interface MoneyFormatter
{
    public function format(Money $amount, LocaleResolution $locale, FormatPolicy $policy): FormattedMoney;
}
