<?php

declare(strict_types=1);

namespace CoreX\Intl\Contracts;

use CoreX\Intl\Data\AddressQuery;
use CoreX\Intl\Data\SuggestionLimits;
use CoreX\Intl\Data\SuggestionResult;

interface AddressSuggestProvider
{
    public function suggest(AddressQuery $query, SuggestionLimits $limits): SuggestionResult;
}
