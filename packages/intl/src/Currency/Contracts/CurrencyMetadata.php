<?php

declare(strict_types=1);

namespace CoreX\Intl\Currency\Contracts;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use CoreX\Intl\Currency\Data\CurrencyInfo;

#[Spi(kind: SpiKind::Implement, since: '1.0')]
interface CurrencyMetadata
{
    public function get(string $currencyCode): CurrencyInfo;
}
