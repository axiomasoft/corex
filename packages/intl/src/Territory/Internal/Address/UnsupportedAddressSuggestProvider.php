<?php

declare(strict_types=1);

namespace CoreX\Intl\Territory\Internal\Address;

use CoreX\Data\Laravel\JsonDocuments;
use CoreX\Intl\Territory\Contracts\AddressSuggestProvider;
use CoreX\Intl\Territory\Data\AddressQuery;
use CoreX\Intl\Territory\Data\SuggestionLimits;
use CoreX\Intl\Territory\Data\SuggestionResult;
use Override;
use Packman\Attributes\Internal;

#[Internal]
final readonly class UnsupportedAddressSuggestProvider implements AddressSuggestProvider
{
    public function __construct(private AddressSuggestGuard $guard) {}

    #[Override]
    public function suggest(AddressQuery $query, SuggestionLimits $limits): SuggestionResult
    {
        if (($invalid = $this->guard->validateQuery($query)) !== null) {
            return $invalid;
        }

        if (($invalid = $this->guard->validateLimits($limits)) !== null) {
            return $invalid;
        }

        return new SuggestionResult(
            status: 'unsupported',
            suggestions: JsonDocuments::capture([]),
            source: null,
            provenance: JsonDocuments::capture(['driver' => 'absent']),
        );
    }
}
