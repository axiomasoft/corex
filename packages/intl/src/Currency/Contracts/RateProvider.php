<?php

declare(strict_types=1);

namespace CoreX\Intl\Currency\Contracts;

use CoreX\Intl\Currency\Data\RateLookup;
use CoreX\Intl\Currency\Data\RateQuery;
use Packman\Attributes\Spi;
use Packman\Attributes\SpiKind;

#[Spi(kind: SpiKind::Implement, since: '1.0')]
interface RateProvider
{
    public function lookup(RateQuery $query): RateLookup;
}
