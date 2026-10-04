<?php

declare(strict_types=1);

namespace CoreX\Search\Internal;

use CoreX\Config\Data\SecretRef;
use CoreX\Config\Laravel\ConfigurationValidator;

/** @internal Package composition type: read only by the provider. */
final readonly class SearchOptions
{
    public function __construct(
        public string $driver,
        public ?SecretRef $cursorSecret,
        public string $textSearchConfig,
        public int $scanBudget,
    ) {}

    /** @param  array<array-key, mixed>  $config */
    public static function fromArray(array $config): self
    {
        $violations = new ConfigurationValidator;

        $driver = $config['driver'] ?? 'null';

        if (! is_string($driver) || $driver === '') {
            $violations->add('driver', 'must be a non-empty string');
            $driver = 'null';
        }

        $secret = $config['cursor_secret'] ?? null;

        if ($secret !== null && ! is_string($secret)) {
            $violations->add('cursor_secret', 'must be null or a string');
            $secret = null;
        }

        $postgres = is_array($config['postgres'] ?? null) ? $config['postgres'] : [];

        $textSearchConfig = $postgres['text_search_config'] ?? 'simple';

        if (! is_string($textSearchConfig) || $textSearchConfig === '') {
            $violations->add('postgres.text_search_config', 'must be a non-empty string');
            $textSearchConfig = 'simple';
        }

        $scanBudget = $postgres['scan_budget'] ?? 1000;

        if (! is_int($scanBudget) || $scanBudget < 1) {
            $violations->add('postgres.scan_budget', 'must be a positive integer');
            $scanBudget = 1000;
        }

        $violations->failIfAny();

        return new self($driver, is_string($secret) && $secret !== '' ? new SecretRef($secret) : null, $textSearchConfig, $scanBudget);
    }
}
