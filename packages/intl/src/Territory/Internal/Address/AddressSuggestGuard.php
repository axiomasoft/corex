<?php

declare(strict_types=1);

namespace CoreX\Intl\Territory\Internal\Address;

use CoreX\Data\Laravel\JsonDocuments;
use CoreX\Intl\Locale\Contracts\LocaleValidation as Bcp47Validator;
use CoreX\Intl\Locale\Exceptions\InvalidLocaleException;
use CoreX\Intl\Territory\Data\AddressQuery;
use CoreX\Intl\Territory\Data\SuggestionLimits;
use CoreX\Intl\Territory\Data\SuggestionResult;

final class AddressSuggestGuard
{
    public function __construct(private readonly Bcp47Validator $locales) {}

    public function validateQuery(AddressQuery $query): ?SuggestionResult
    {
        $text = trim($query->text);
        $consent = trim($query->consentReference);

        if ($text === '' || strlen($text) > 200) {
            return $this->invalid(reason: 'text_bounds');
        }

        if ($consent === '' || strlen($consent) > 128) {
            return $this->invalid(reason: 'consent_bounds');
        }

        if ($query->country !== null && preg_match('/\A[A-Z]{2}\z/', $query->country) !== 1) {
            return $this->invalid(reason: 'country_format');
        }

        try {
            $this->locales->validate($query->locale);
        } catch (InvalidLocaleException) {
            return $this->invalid(reason: 'locale');
        }

        return null;
    }

    public function validateLimits(SuggestionLimits $limits): ?SuggestionResult
    {
        if ($limits->timeoutSeconds < 1 || $limits->timeoutSeconds > 2) {
            return $this->invalid(reason: 'timeout_bounds');
        }

        if ($limits->results < 1 || $limits->results > 20) {
            return $this->invalid(reason: 'results_bounds');
        }

        if ($limits->maxResponseBytes < 1 || $limits->maxResponseBytes > 65536) {
            return $this->invalid(reason: 'response_bounds');
        }

        return null;
    }

    public function normalizeLimits(SuggestionLimits $limits): SuggestionLimits
    {
        return new SuggestionLimits(
            timeoutSeconds: min(max(1, $limits->timeoutSeconds), 2),
            results: min(max(1, $limits->results), 20),
            maxResponseBytes: min(max(1, $limits->maxResponseBytes), 65536),
        );
    }

    private function invalid(string $reason): SuggestionResult
    {
        return new SuggestionResult(
            status: 'invalid',
            suggestions: JsonDocuments::capture([]),
            source: null,
            provenance: JsonDocuments::capture(['reason' => $reason]),
        );
    }
}
