<?php

declare(strict_types=1);

namespace CoreX\Search\Internal;

use Packman\Attributes\Internal;
use Packman\Config\Data\SecretRef;
use Packman\Config\Laravel\ConfigurationValidator;

/** @internal Package composition type: read only by the provider. */
#[Internal]
final readonly class SearchOptions
{
    public function __construct(
        public string $driver,
        public string $cluster,
        public int $retentionSeconds,
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
            $violations->add(path: 'driver', message: 'must be a non-empty string');
            $driver = 'null';
        }

        $cluster = $config['cluster'] ?? 'primary';

        if (! is_string($cluster) || preg_match('/\A[A-Za-z0-9][A-Za-z0-9._-]{0,127}\z/D', $cluster) !== 1) {
            $violations->add(path: 'cluster', message: 'must be a bounded cluster identifier');
            $cluster = 'primary';
        }

        $retention = $config['retention_seconds'] ?? 604800;

        if (! is_int($retention) || $retention < 1) {
            $violations->add(path: 'retention_seconds', message: 'must be a positive integer');
            $retention = 604800;
        }

        $secret = $config['cursor_secret'] ?? null;

        if ($secret !== null && ! is_string($secret)) {
            $violations->add(path: 'cursor_secret', message: 'must be null or a string');
            $secret = null;
        }

        $postgres = is_array($config['postgres'] ?? null) ? $config['postgres'] : [];

        $textSearchConfig = $postgres['text_search_config'] ?? 'simple';

        if (! is_string($textSearchConfig) || $textSearchConfig === '') {
            $violations->add(path: 'postgres.text_search_config', message: 'must be a non-empty string');
            $textSearchConfig = 'simple';
        }

        $scanBudget = $postgres['scan_budget'] ?? 1000;

        if (! is_int($scanBudget) || $scanBudget < 1 || $scanBudget > 1000) {
            $violations->add(path: 'postgres.scan_budget', message: 'must be an integer from 1 to 1000');
            $scanBudget = 1000;
        }

        $violations->failIfAny();

        return new self(driver: $driver, cluster: $cluster, retentionSeconds: $retention, cursorSecret: is_string($secret) && $secret !== '' ? new SecretRef($secret) : null, textSearchConfig: $textSearchConfig, scanBudget: $scanBudget);
    }
}
