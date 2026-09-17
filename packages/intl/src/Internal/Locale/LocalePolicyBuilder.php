<?php

declare(strict_types=1);

namespace CoreX\Intl\Internal\Locale;

use CoreX\Intl\Data\LocalePolicy;
use InvalidArgumentException;
use JsonException;
use RuntimeException;

final class LocalePolicyBuilder
{
    /**
     * @param  array<string, mixed>  $config
     */
    public function build(array $config): LocalePolicy
    {
        $supportedTags = array_values(array_unique(array_map(
            static fn (mixed $tag): string => is_string($tag) ? $tag : '',
            $config['supported_tags'] ?? [],
        )));

        $supportedTags = array_values(array_filter($supportedTags, static fn (string $tag): bool => $tag !== ''));

        if ($supportedTags === []) {
            throw new InvalidArgumentException('Locale supported tags allowlist must be non-empty.');
        }

        if (count($supportedTags) > (int) ($config['max_supported_tags'] ?? 100)) {
            throw new InvalidArgumentException('Locale supported tags exceed the configured maximum.');
        }

        $validator = new Bcp47Validator(
            supportedTags: $supportedTags,
            maxTagLength: (int) ($config['max_tag_length'] ?? 128),
        );

        $canonicalSupported = array_map(
            fn (string $tag): string => $validator->validate(tag: $tag),
            $supportedTags,
        );

        $appTag = $validator->validate(tag: (string) ($config['app_tag'] ?? 'en'));

        $fallbackTags = array_values(array_unique(array_map(
            static fn (mixed $tag): string => is_string($tag) ? $tag : '',
            $config['explicit_fallback_tags'] ?? [],
        )));

        $canonicalFallback = array_map(
            fn (string $tag): string => $validator->validate(tag: $tag),
            array_values(array_filter($fallbackTags, static fn (string $tag): bool => $tag !== '')),
        );

        return new LocalePolicy(
            supportedTags: $canonicalSupported,
            appTag: $appTag,
            explicitFallbackTags: $canonicalFallback,
            allowParent: (bool) ($config['allow_parent'] ?? true),
            policyHash: $this->hashPolicy(
                supportedTags: $canonicalSupported,
                appTag: $appTag,
                explicitFallbackTags: $canonicalFallback,
                allowParent: (bool) ($config['allow_parent'] ?? true),
            ),
        );
    }

    /**
     * @param  list<string>  $supportedTags
     * @param  list<string>  $explicitFallbackTags
     */
    private function hashPolicy(
        array $supportedTags,
        string $appTag,
        array $explicitFallbackTags,
        bool $allowParent,
    ): string {
        try {
            return hash('sha256', json_encode([
                'supportedTags' => $supportedTags,
                'appTag' => $appTag,
                'explicitFallbackTags' => $explicitFallbackTags,
                'allowParent' => $allowParent,
            ], JSON_THROW_ON_ERROR));
        } catch (JsonException $exception) {
            throw new RuntimeException('Locale policy hash could not be encoded.', previous: $exception);
        }
    }
}
