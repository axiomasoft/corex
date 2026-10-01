<?php

declare(strict_types=1);

namespace CoreX\Intl\Territory\Contracts;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use CoreX\Intl\Territory\Data\AddressQuery;
use CoreX\Intl\Territory\Data\SuggestionLimits;
use CoreX\Intl\Territory\Data\SuggestionResult;

#[Spi(kind: SpiKind::Implement, since: '1.0')]
interface AddressSuggestProvider
{
    public function suggest(AddressQuery $query, SuggestionLimits $limits): SuggestionResult;
}
