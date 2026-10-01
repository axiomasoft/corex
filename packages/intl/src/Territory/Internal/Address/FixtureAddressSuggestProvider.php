<?php

declare(strict_types=1);

namespace CoreX\Intl\Territory\Internal\Address;

use CoreX\Data\Laravel\JsonDocuments;
use CoreX\Intl\Territory\Contracts\AddressSuggestProvider;
use CoreX\Intl\Territory\Data\AddressQuery;
use CoreX\Intl\Territory\Data\SuggestionLimits;
use CoreX\Intl\Territory\Data\SuggestionResult;
use InvalidArgumentException;
use Override;

final class FixtureAddressSuggestProvider implements AddressSuggestProvider
{
    /** @var list<array<string, mixed>> */
    private readonly array $entries;

    public function __construct(
        private readonly string $fixturePath,
        private readonly AddressSuggestGuard $guard,
    ) {
        if (! is_file($fixturePath)) {
            throw new InvalidArgumentException('Address fixture is missing.');
        }

        $decoded = json_decode((string) file_get_contents($fixturePath), true, flags: JSON_THROW_ON_ERROR);

        if (! is_array($decoded) || ! is_array($decoded['entries'] ?? null)) {
            throw new InvalidArgumentException('Address fixture is malformed.');
        }

        $this->entries = $decoded['entries'];
    }

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

        $needle = mb_strtolower(trim($query->text));
        $matches = [];

        foreach ($this->entries as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            $entryText = mb_strtolower((string) ($entry['text'] ?? ''));
            $entryLocale = (string) ($entry['locale'] ?? '');
            $entryCountry = isset($entry['country']) ? (string) $entry['country'] : null;

            if ($entryText !== $needle || $entryLocale !== $query->locale) {
                continue;
            }

            if ($query->country !== null && $entryCountry !== null && $query->country !== $entryCountry) {
                continue;
            }

            foreach ($entry['suggestions'] ?? [] as $suggestion) {
                if (! is_array($suggestion)) {
                    continue;
                }

                $matches[] = [
                    'label' => (string) ($suggestion['label'] ?? ''),
                    'value' => (string) ($suggestion['value'] ?? ''),
                ];

                if (count($matches) >= $limits->results) {
                    break 2;
                }
            }
        }

        $payload = json_encode($matches, JSON_THROW_ON_ERROR);

        if (strlen($payload) > $limits->maxResponseBytes) {
            return new SuggestionResult(
                status: 'unavailable',
                suggestions: JsonDocuments::capture([]),
                source: 'fixture-address/1',
                provenance: JsonDocuments::capture(['reason' => 'response_bounds']),
            );
        }

        if ($matches === []) {
            return new SuggestionResult(
                status: 'notFound',
                suggestions: JsonDocuments::capture([]),
                source: 'fixture-address/1',
                provenance: JsonDocuments::capture(['consentReference' => $query->consentReference]),
            );
        }

        return new SuggestionResult(
            status: 'available',
            suggestions: JsonDocuments::capture($matches),
            source: 'fixture-address/1',
            provenance: JsonDocuments::capture(['consentReference' => $query->consentReference]),
        );
    }
}
