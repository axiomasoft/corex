<?php

declare(strict_types=1);

namespace CoreX\Intl\Territory\Internal\Address;

use CoreX\Intl\Territory\Contracts\AddressSuggestProvider;
use CoreX\Intl\Territory\Data\AddressQuery;
use CoreX\Intl\Territory\Data\SuggestionLimits;
use CoreX\Intl\Territory\Data\SuggestionResult;
use Override;

final class MapAddressSuggestProvider implements AddressSuggestProvider
{
    /**
     * @param  array<string, list<array<string, string>>>  $responses
     */
    public function __construct(
        private readonly string $source,
        private readonly array $responses,
        private readonly AddressSuggestGuard $guard,
    ) {}

    #[Override]
    public function suggest(AddressQuery $query, SuggestionLimits $limits): SuggestionResult
    {
        if ($invalid = $this->guard->validateQuery($query)) {
            return $invalid;
        }

        $limits = $this->guard->normalizeLimits($limits);

        if ($invalid = $this->guard->validateLimits($limits)) {
            return $invalid;
        }

        $key = mb_strtolower(trim($query->text)).'|'.$query->locale;

        if (! array_key_exists($key, $this->responses)) {
            return new SuggestionResult(
                status: 'notFound',
                suggestions: [],
                source: $this->source,
                provenance: ['consentReference' => $query->consentReference],
            );
        }

        $suggestions = array_slice($this->responses[$key], 0, $limits->results);
        $payload = json_encode($suggestions, JSON_THROW_ON_ERROR);

        if (strlen($payload) > $limits->maxResponseBytes) {
            return new SuggestionResult(
                status: 'rateLimited',
                suggestions: [],
                source: $this->source,
                provenance: ['reason' => 'response_bounds'],
            );
        }

        return new SuggestionResult(
            status: 'available',
            suggestions: $suggestions,
            source: $this->source,
            provenance: ['consentReference' => $query->consentReference],
        );
    }
}
