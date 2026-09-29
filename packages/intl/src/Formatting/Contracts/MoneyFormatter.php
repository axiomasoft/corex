<?php

declare(strict_types=1);

namespace CoreX\Intl\Formatting\Contracts;

use Brick\Money\Money;
use CoreX\Intl\Formatting\Data\FormatPolicy;
use CoreX\Intl\Formatting\Data\FormattedMoney;
use CoreX\Intl\Locale\Data\LocaleResolution;

interface MoneyFormatter
{
    public function format(Money $amount, LocaleResolution $locale, FormatPolicy $policy): FormattedMoney;
}
