<?php

declare(strict_types=1);

namespace CoreX\Intl\Currency\Contracts;

use CoreX\Intl\Currency\Data\CurrencyInfo;

interface CurrencyMetadata
{
    public function get(string $currencyCode): CurrencyInfo;
}
