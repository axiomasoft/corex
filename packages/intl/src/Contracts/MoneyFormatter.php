<?php

declare(strict_types=1);

namespace CoreX\Intl\Contracts;

use Brick\Money\Money;
use CoreX\Intl\Data\FormatPolicy;
use CoreX\Intl\Data\FormattedMoney;
use CoreX\Intl\Data\LocaleResolution;

interface MoneyFormatter
{
    public function format(Money $amount, LocaleResolution $locale, FormatPolicy $policy): FormattedMoney;
}
