<?php

declare(strict_types=1);

namespace CoreX\Intl\Territory\Internal\Address;

use CoreX\Data\Laravel\JsonDocuments;
use CoreX\Intl\Territory\Contracts\AddressSuggestProvider;
use CoreX\Intl\Territory\Data\AddressQuery;
use CoreX\Intl\Territory\Data\SuggestionLimits;
use CoreX\Intl\Territory\Data\SuggestionResult;
use Override;

final readonly class MapAddressSuggestProvider implements AddressSuggestProvider
{
    /**
     * @param  array<string, list<array<string, string>>>  $responses
     */
    public function __construct(
        private string $source,
        private array $responses,
        private AddressSuggestGuard $guard,
    ) {}

    #[Override]
    public function suggest(AddressQuery $query, SuggestionLimits $limits): SuggestionResult
    {
        if (($invalid = $this->guard->validateQuery($query)) !== null) {
            return $invalid;
        }

        $limits = $this->guard->normalizeLimits($limits);

        if (($invalid = $this->guard->validateLimits($limits)) !== null) {
            return $invalid;
        }

        $key = mb_strtolower(trim($query->text)).'|'.$query->locale;

        if (! array_key_exists($key, $this->responses)) {
            return new SuggestionResult(
                status: 'notFound',
                suggestions: JsonDocuments::capture([]),
                source: $this->source,
                provenance: JsonDocuments::capture(['consentReference' => $query->consentReference]),
            );
        }

        $suggestions = array_slice($this->responses[$key], 0, $limits->results);
        $payload = json_encode($suggestions, JSON_THROW_ON_ERROR);

        if (strlen($payload) > $limits->maxResponseBytes) {
            return new SuggestionResult(
                status: 'rateLimited',
                suggestions: JsonDocuments::capture([]),
                source: $this->source,
                provenance: JsonDocuments::capture(['reason' => 'response_bounds']),
            );
        }

        return new SuggestionResult(
            status: 'available',
            suggestions: JsonDocuments::capture($suggestions),
            source: $this->source,
            provenance: JsonDocuments::capture(['consentReference' => $query->consentReference]),
        );
    }
}
