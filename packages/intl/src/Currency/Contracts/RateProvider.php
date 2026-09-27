<?php

declare(strict_types=1);

namespace CoreX\Intl\Contracts;

use CoreX\Intl\Data\RateLookup;
use CoreX\Intl\Data\RateQuery;

interface RateProvider
{
    public function lookup(RateQuery $query): RateLookup;
}
