<?php

declare(strict_types=1);

namespace CoreX\Intl\Currency\Contracts;

use CoreX\Intl\Currency\Data\RateLookup;
use CoreX\Intl\Currency\Data\RateQuery;

interface RateProvider
{
    public function lookup(RateQuery $query): RateLookup;
}
