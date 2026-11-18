<?php

declare(strict_types=1);

namespace CoreX\Intl\Currency\Contracts;

use CoreX\Intl\Currency\Data\CurrencyInfo;
use Packman\Attributes\Spi;
use Packman\Attributes\SpiKind;

#[Spi(kind: SpiKind::Implement, since: '1.0')]
interface CurrencyMetadata
{
    public function get(string $currencyCode): CurrencyInfo;
}
