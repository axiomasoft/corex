<?php

declare(strict_types=1);

namespace CoreX\Intl\Territory\Internal\Address;

use CoreX\Intl\Territory\Contracts\AddressSuggestProvider;
use CoreX\Intl\Territory\Data\AddressQuery;
use CoreX\Intl\Territory\Data\SuggestionLimits;
use CoreX\Intl\Territory\Data\SuggestionResult;
use Override;

final class UnsupportedAddressSuggestProvider implements AddressSuggestProvider
{
    public function __construct(private readonly AddressSuggestGuard $guard) {}

    #[Override]
    public function suggest(AddressQuery $query, SuggestionLimits $limits): SuggestionResult
    {
        if ($invalid = $this->guard->validateQuery($query)) {
            return $invalid;
        }

        if ($invalid = $this->guard->validateLimits($limits)) {
            return $invalid;
        }

        return new SuggestionResult(
            status: 'unsupported',
            suggestions: [],
            source: null,
            provenance: ['driver' => 'absent'],
        );
    }
}
