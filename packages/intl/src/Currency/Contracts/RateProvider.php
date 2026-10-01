<?php

declare(strict_types=1);

namespace CoreX\Intl\Currency\Contracts;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use CoreX\Intl\Currency\Data\RateLookup;
use CoreX\Intl\Currency\Data\RateQuery;

#[Spi(kind: SpiKind::Implement, since: '1.0')]
interface RateProvider
{
    public function lookup(RateQuery $query): RateLookup;
}
