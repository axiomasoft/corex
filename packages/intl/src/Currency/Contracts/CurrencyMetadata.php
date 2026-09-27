<?php

declare(strict_types=1);

namespace CoreX\Intl\Contracts;

use CoreX\Intl\Data\CurrencyInfo;

interface CurrencyMetadata
{
    public function get(string $currencyCode): CurrencyInfo;
}
