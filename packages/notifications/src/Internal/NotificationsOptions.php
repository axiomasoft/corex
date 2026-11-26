<?php

declare(strict_types=1);

namespace CoreX\Notifications\Internal;

use Packman\Attributes\Internal;
use Packman\Config\Laravel\ConfigurationValidator;

/** @internal Package composition type: read only by the provider and runtime checks. */
#[Internal]
final readonly class NotificationsOptions
{
    public function __construct(
        public bool $digestEnabled,
        public int $deliveryClaimSeconds,
        public int $deliveryMaxAttempts,
        public int $retentionSettledDays,
    ) {}

    /** @param  array<array-key, mixed>  $config */
    public static function fromArray(array $config): self
    {
        $violations = new ConfigurationValidator;
        $digest = is_array($config['digest'] ?? null) ? $config['digest'] : [];
        $delivery = is_array($config['delivery'] ?? null) ? $config['delivery'] : [];
        $retention = array_key_exists('retention', $config) ? $config['retention'] : [];

        if (! is_array($retention)) {
            $violations->add(path: 'retention', message: 'must be a configuration map');
            $retention = [];
        }

        if (array_diff_key($retention, ['settled_days' => true]) !== []) {
            $violations->add(path: 'retention', message: 'only settled_days is supported');
        }

        $digestEnabled = self::bool(violations: $violations, path: 'digest.enabled', value: $digest['enabled'] ?? false);
        $claimSeconds = self::positiveInt(violations: $violations, path: 'delivery.claim_seconds', value: $delivery['claim_seconds'] ?? 60);
        $maxAttempts = self::positiveInt(violations: $violations, path: 'delivery.max_attempts', value: $delivery['max_attempts'] ?? 5);
        $settledDays = self::positiveInt(violations: $violations, path: 'retention.settled_days', value: array_key_exists('settled_days', $retention) ? $retention['settled_days'] : 90);

        $violations->failIfAny();

        return new self(digestEnabled: $digestEnabled, deliveryClaimSeconds: $claimSeconds, deliveryMaxAttempts: $maxAttempts, retentionSettledDays: $settledDays);
    }

    private static function bool(ConfigurationValidator $violations, string $path, mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        $violations->add(path: $path, message: 'must be a boolean');

        return false;
    }

    private static function positiveInt(ConfigurationValidator $violations, string $path, mixed $value): int
    {
        if (is_int($value) && $value > 0) {
            return $value;
        }

        $violations->add(path: $path, message: 'must be a positive integer');

        return 1;
    }
}
