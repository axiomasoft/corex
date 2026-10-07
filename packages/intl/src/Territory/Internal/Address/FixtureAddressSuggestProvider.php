<?php

declare(strict_types=1);

namespace CoreX\Intl\Territory\Internal\Address;

use CoreX\Data\Laravel\JsonDocuments;
use CoreX\Intl\Territory\Contracts\AddressSuggestProvider;
use CoreX\Intl\Territory\Data\AddressQuery;
use CoreX\Intl\Territory\Data\SuggestionLimits;
use CoreX\Intl\Territory\Data\SuggestionResult;
use CoreX\Intl\Territory\Internal\Value;
use InvalidArgumentException;
use Override;

final readonly class FixtureAddressSuggestProvider implements AddressSuggestProvider
{
    /** @var list<array<string, mixed>> */
    private array $entries;

    public function __construct(
        private string $fixturePath,
        private AddressSuggestGuard $guard,
    ) {
        if (! is_file($fixturePath)) {
            throw new InvalidArgumentException('Address fixture is missing.');
        }

        $decoded = json_decode((string) file_get_contents($fixturePath), true, flags: JSON_THROW_ON_ERROR);

        if (! is_array($decoded) || ! is_array($decoded['entries'] ?? null)) {
            throw new InvalidArgumentException('Address fixture is malformed.');
        }

        $this->entries = Value::maps($decoded['entries']);
    }

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

        $needle = mb_strtolower(trim($query->text));
        $matches = [];

        foreach ($this->entries as $entry) {

            $entryText = mb_strtolower(Value::text($entry['text'] ?? ''));
            $entryLocale = Value::text($entry['locale'] ?? '');
            $entryCountry = isset($entry['country']) ? Value::text($entry['country']) : null;

            if ($entryText !== $needle || $entryLocale !== $query->locale) {
                continue;
            }

            if ($query->country !== null && $entryCountry !== null && $query->country !== $entryCountry) {
                continue;
            }

            foreach (Value::array($entry['suggestions'] ?? []) as $suggestion) {
                if (! is_array($suggestion)) {
                    continue;
                }

                $matches[] = [
                    'label' => Value::text($suggestion['label'] ?? ''),
                    'value' => Value::text($suggestion['value'] ?? ''),
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
