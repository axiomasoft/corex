<?php

declare(strict_types=1);

namespace CoreX\Notifications\Internal;

use CoreX\Config\Laravel\ConfigurationValidator;

/** @internal Package composition type: read only by the provider and runtime checks. */
final readonly class NotificationsOptions
{
    public function __construct(
        public bool $digestEnabled,
        public bool $digestAcceptLegacyUnstamped,
        public int $deliveryClaimSeconds,
        public int $deliveryMaxAttempts,
    ) {}

    /** @param  array<array-key, mixed>  $config */
    public static function fromArray(array $config): self
    {
        $violations = new ConfigurationValidator;
        $digest = is_array($config['digest'] ?? null) ? $config['digest'] : [];
        $delivery = is_array($config['delivery'] ?? null) ? $config['delivery'] : [];

        $digestEnabled = self::bool($violations, 'digest.enabled', $digest['enabled'] ?? false);
        $acceptLegacy = self::bool($violations, 'digest.accept_legacy_unstamped', $digest['accept_legacy_unstamped'] ?? false);
        $claimSeconds = self::positiveInt($violations, 'delivery.claim_seconds', $delivery['claim_seconds'] ?? 60);
        $maxAttempts = self::positiveInt($violations, 'delivery.max_attempts', $delivery['max_attempts'] ?? 5);

        $violations->failIfAny();

        return new self($digestEnabled, $acceptLegacy, $claimSeconds, $maxAttempts);
    }

    /** @return list<string> config paths of enabled dangerous modes */
    public function dangerousModes(): array
    {
        return $this->digestAcceptLegacyUnstamped ? ['corex-notifications.digest.accept_legacy_unstamped'] : [];
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
