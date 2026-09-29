<?php

declare(strict_types=1);

namespace CoreX\Intl\Territory\Contracts;

use CoreX\Intl\Territory\Data\AddressQuery;
use CoreX\Intl\Territory\Data\SuggestionLimits;
use CoreX\Intl\Territory\Data\SuggestionResult;

interface AddressSuggestProvider
{
    public function suggest(AddressQuery $query, SuggestionLimits $limits): SuggestionResult;
}
