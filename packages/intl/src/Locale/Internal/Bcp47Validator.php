<?php

declare(strict_types=1);

namespace CoreX\Intl\Locale\Internal;

use CoreX\Intl\Locale\Contracts\LocaleValidation;
use CoreX\Intl\Locale\Exceptions\InvalidLocaleException;
use Locale;
use Packman\Attributes\Internal;

#[Internal]
final readonly class Bcp47Validator implements LocaleValidation
{
    /**
     * @param  list<string>  $supportedTags
     */
    public function __construct(
        private array $supportedTags,
        private int $maxTagLength = 128,
    ) {}

    public function canonicalizeTag(string $tag): string
    {
        $trimmed = trim($tag);

        if ($trimmed === '' || strlen($trimmed) > $this->maxTagLength) {
            throw new InvalidLocaleException('Locale tag is empty or exceeds the maximum length.');
        }

        if (preg_match('/\A[a-zA-Z0-9]+(?:-[a-zA-Z0-9]+)*\z/', $trimmed) !== 1) {
            throw new InvalidLocaleException('Locale tag contains invalid BCP47 characters.');
        }

        return $this->canonicalize(tag: $trimmed);
    }

    public function validate(string $tag): string
    {
        $trimmed = trim($tag);

        if ($trimmed === '' || strlen($trimmed) > $this->maxTagLength) {
            throw new InvalidLocaleException('Locale tag is empty or exceeds the maximum length.');
        }

        if (preg_match('/\A[a-zA-Z0-9]+(?:-[a-zA-Z0-9]+)*\z/', $trimmed) !== 1) {
            throw new InvalidLocaleException('Locale tag contains invalid BCP47 characters.');
        }

        $canonical = $this->canonicalize(tag: $trimmed);

        if (! $this->isSupported(tag: $canonical)) {
            throw new InvalidLocaleException('Locale tag is not in the supported allowlist.');
        }

        return $canonical;
    }

    public function canonicalize(string $tag): string
    {
        if (class_exists(Locale::class)) {
            $canonical = Locale::canonicalize($tag);

            if (is_string($canonical)) {
                return str_replace('_', '-', $canonical);
            }
        }

        $parts = explode('-', $tag);
        $language = strtolower($parts[0]);
        $rest = array_slice($parts, 1);
        $normalized = [$language];

        foreach ($rest as $part) {
            if (strlen($part) === 2) {
                $normalized[] = strtoupper($part);

                continue;
            }

            if (strlen($part) === 4) {
                $normalized[] = ucfirst(strtolower($part));

                continue;
            }

            $normalized[] = strtoupper($part);
        }

        return implode('-', $normalized);
    }

    public function isSupported(string $tag): bool
    {
        return in_array($tag, $this->supportedTags, true);
    }

    /**
     * @return list<string>
     */
    public function parentChain(string $tag): array
    {
        $chain = [];
        $current = $tag;

        while (($pos = strrpos($current, '-')) !== false) {
            $current = substr($current, 0, $pos);
            $chain[] = $current;
        }

        return $chain;
    }
}
