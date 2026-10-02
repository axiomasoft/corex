<?php

declare(strict_types=1);

namespace CoreX\Intl\Internal;

use CoreX\Config\Data\SecretRef;
use CoreX\Config\Laravel\ConfigurationValidator;

/**
 * Shape of `corex-intl`. Locale semantics (BCP 47, fallback closure) are checked when the policy is built.
 *
 * @internal Package composition type: read only by the provider and registrars.
 */
final readonly class IntlOptions
{
    /**
     * @param  list<string>  $localeSupportedTags
     * @param  list<string>  $localeExplicitFallbackTags
     * @param  array<string, array<array-key, mixed>>  $referenceProviders
     */
    public function __construct(
        public array $localeSupportedTags,
        public string $localeAppTag,
        public array $localeExplicitFallbackTags,
        public bool $localeAllowParent,
        public string $localeSettingsNamespace,
        public string $localeSettingsKey,
        public int $localeMaxSupportedTags,
        public int $localeMaxTagLength,
        public string $moneyMetadataVersion,
        public string $moneyDefaultSource,
        public int $moneyBatchLimit,
        public string $referenceDatasetKey,
        public SecretRef $referenceCursorSecret,
        public array $referenceProviders,
        public bool $formattingIcuEnabled,
    ) {}

    /** @param  array<array-key, mixed>  $config */
    public static function fromArray(array $config): self
    {
        $violations = new ConfigurationValidator;
        $locale = is_array($config['locale'] ?? null) ? $config['locale'] : [];
        $money = is_array($config['money'] ?? null) ? $config['money'] : [];
        $reference = is_array($config['reference'] ?? null) ? $config['reference'] : [];
        $formatting = is_array($config['formatting'] ?? null) ? $config['formatting'] : [];

        $supportedTags = self::stringList($violations, 'locale.supported_tags', $locale['supported_tags'] ?? ['en', 'en-US', 'ru', 'ru-RU']);
        $appTag = self::nonEmpty($violations, 'locale.app_tag', $locale['app_tag'] ?? 'en');
        $fallbackTags = self::stringList($violations, 'locale.explicit_fallback_tags', $locale['explicit_fallback_tags'] ?? ['en']);
        $allowParent = self::bool($violations, 'locale.allow_parent', $locale['allow_parent'] ?? true);
        $settingsNamespace = self::nonEmpty($violations, 'locale.settings_namespace', $locale['settings_namespace'] ?? 'intl');
        $settingsKey = self::nonEmpty($violations, 'locale.settings_key', $locale['settings_key'] ?? 'locale');
        $maxSupportedTags = self::positiveInt($violations, 'locale.max_supported_tags', $locale['max_supported_tags'] ?? 100);
        $maxTagLength = self::positiveInt($violations, 'locale.max_tag_length', $locale['max_tag_length'] ?? 128);

        $metadataVersion = self::nonEmpty($violations, 'money.metadata_version', $money['metadata_version'] ?? 'brick/1');
        $defaultSource = self::nonEmpty($violations, 'money.default_source', $money['default_source'] ?? 'manual');
        $batchLimit = self::positiveInt($violations, 'money.batch_limit', $money['batch_limit'] ?? 1000);

        $datasetKey = self::nonEmpty($violations, 'reference.dataset_key', $reference['dataset_key'] ?? 'geography');
        $cursorSecret = $reference['cursor_secret'] ?? '';

        if (! is_string($cursorSecret)) {
            $violations->add('reference.cursor_secret', 'must be a string');
            $cursorSecret = '';
        }
        $configuredProviders = $reference['providers'] ?? [];

        if (! is_array($configuredProviders)) {
            $violations->add('reference.providers', 'must be a map');
            $configuredProviders = [];
        }

        $providers = [];

        foreach ($configuredProviders as $name => $definition) {
            if (! is_string($name) || ! is_array($definition)) {
                $violations->add('reference.providers', 'must map each provider name to its settings');

                continue;
            }

            $providers[$name] = $definition;
        }

        $icuEnabled = self::bool($violations, 'formatting.icu_enabled', $formatting['icu_enabled'] ?? false);

        $violations->failIfAny();

        return new self(
            localeSupportedTags: $supportedTags,
            localeAppTag: $appTag,
            localeExplicitFallbackTags: $fallbackTags,
            localeAllowParent: $allowParent,
            localeSettingsNamespace: $settingsNamespace,
            localeSettingsKey: $settingsKey,
            localeMaxSupportedTags: $maxSupportedTags,
            localeMaxTagLength: $maxTagLength,
            moneyMetadataVersion: $metadataVersion,
            moneyDefaultSource: $defaultSource,
            moneyBatchLimit: $batchLimit,
            referenceDatasetKey: $datasetKey,
            referenceCursorSecret: new SecretRef($cursorSecret),
            referenceProviders: $providers,
            formattingIcuEnabled: $icuEnabled,
        );
    }

    /** @return array<string, mixed> input of the locale policy builder */
    public function localePolicyConfig(): array
    {
        return [
            'supported_tags' => $this->localeSupportedTags,
            'app_tag' => $this->localeAppTag,
            'explicit_fallback_tags' => $this->localeExplicitFallbackTags,
            'allow_parent' => $this->localeAllowParent,
            'max_supported_tags' => $this->localeMaxSupportedTags,
            'max_tag_length' => $this->localeMaxTagLength,
        ];
    }

    /** @return list<string> */
    private static function stringList(ConfigurationValidator $violations, string $path, mixed $value): array
    {
        if (! is_array($value) || ! array_is_list($value)) {
            $violations->add($path, 'must be a list of strings');

            return [];
        }

        $items = [];
        foreach ($value as $index => $item) {
            if (! is_string($item)) {
                $violations->add($path.'.'.$index, 'must be a string');

                continue;
            }

            $items[] = $item;
        }

        return $items;
    }

    private static function nonEmpty(ConfigurationValidator $violations, string $path, mixed $value): string
    {
        if (is_string($value) && $value !== '') {
            return $value;
        }

        $violations->add($path, 'must be a non-empty string');

        return 'invalid';
    }

    private static function bool(ConfigurationValidator $violations, string $path, mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        $violations->add($path, 'must be a boolean');

        return false;
    }

    private static function positiveInt(ConfigurationValidator $violations, string $path, mixed $value): int
    {
        if (is_int($value) && $value > 0) {
            return $value;
        }

        $violations->add($path, 'must be a positive integer');

        return 1;
    }
}
