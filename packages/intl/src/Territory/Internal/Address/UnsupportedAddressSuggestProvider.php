<?php

declare(strict_types=1);

namespace CoreX\Intl\Internal\Address;

use CoreX\Intl\Contracts\AddressSuggestProvider;
use CoreX\Intl\Data\AddressQuery;
use CoreX\Intl\Data\SuggestionLimits;
use CoreX\Intl\Data\SuggestionResult;
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
