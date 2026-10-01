<?php

declare(strict_types=1);

namespace CoreX\Operations\Cost\Laravel;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use CoreX\Config\Data\SecretRef;
use CoreX\Config\Laravel\ConfigurationValidator;
use CoreX\Operations\Cost\Data\CostOptions;

/** Immutable cost-control activation policy read only by the package registrar. */
#[Spi(kind: SpiKind::Call, since: '1.0')]
final class CostOptionsParser
{
    /** @param array<array-key, mixed> $config */
    public static function fromArray(array $config): CostOptions
    {
        $violations = new ConfigurationValidator;
        $enabled = self::bool($violations, 'enabled', $config['enabled'] ?? false);
        $installationId = self::nonEmpty($violations, 'installation_id', $config['installation_id'] ?? 'corex');
        $scopeHmacSecret = self::secret($violations, $config['scope_hmac_secret'] ?? '');
        $capacity = self::boundedPositiveInt($violations, 'capacity', $config['capacity'] ?? 10_000);
        $restoreRate = self::boundedPositiveInt($violations, 'restore_rate', $config['restore_rate'] ?? 1_000);
        $enforcedLevels = self::enforcedLevels($violations, $config['enforced_levels'] ?? ['global']);
        $reservationTtl = self::positiveInt($violations, 'reservation_ttl_seconds', $config['reservation_ttl_seconds'] ?? 3_600);
        $terminalRetention = self::positiveInt($violations, 'terminal_retention_seconds', $config['terminal_retention_seconds'] ?? 2_592_000);

        if ($enabled && ! $scopeHmacSecret->isPresent()) {
            $violations->add('scope_hmac_secret', 'must be configured when cost enforcement is enabled');
        }

        if ($reservationTtl > 3_600) {
            $violations->add('reservation_ttl_seconds', 'must not exceed 3600');
        }

        if ($terminalRetention < $reservationTtl) {
            $violations->add('terminal_retention_seconds', 'must not be shorter than reservation_ttl_seconds');
        }

        $violations->failIfAny();

        return new CostOptions(
            enabled: $enabled,
            installationId: $installationId,
            scopeHmacSecret: $scopeHmacSecret,
            capacity: $capacity,
            restoreRate: $restoreRate,
            enforcedLevels: $enforcedLevels,
            reservationTtlSeconds: $reservationTtl,
            terminalRetentionSeconds: $terminalRetention,
        );
    }

    private static function bool(ConfigurationValidator $violations, string $path, mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        $violations->add($path, 'must be a boolean');

        return false;
    }

    private static function nonEmpty(ConfigurationValidator $violations, string $path, mixed $value): string
    {
        if (is_string($value) && $value !== '') {
            return $value;
        }

        $violations->add($path, 'must be a non-empty string');

        return 'invalid';
    }

    private static function secret(ConfigurationValidator $violations, mixed $value): SecretRef
    {
        if ($value instanceof SecretRef) {
            return $value;
        }

        if (is_string($value)) {
            return new SecretRef($value);
        }

        $violations->add('scope_hmac_secret', 'must be a SecretRef or string');

        return new SecretRef('');
    }

    private static function positiveInt(ConfigurationValidator $violations, string $path, mixed $value): int
    {
        if (is_int($value) && $value > 0) {
            return $value;
        }

        $violations->add($path, 'must be a positive integer');

        return 1;
    }

    private static function boundedPositiveInt(ConfigurationValidator $violations, string $path, mixed $value): int
    {
        $resolved = self::positiveInt($violations, $path, $value);

        if ($resolved > intdiv(PHP_INT_MAX, 1_000_000)) {
            $violations->add($path, 'is outside the safe micro-unit range');

            return 1;
        }

        return $resolved;
    }

    /** @return list<string> */
    private static function enforcedLevels(ConfigurationValidator $violations, mixed $value): array
    {
        $known = ['global', 'account', 'client', 'actor'];

        if (! is_array($value) || ! array_is_list($value)) {
            $violations->add('enforced_levels', 'must be a list containing global');

            return ['global'];
        }

        foreach ($value as $level) {
            if (! is_string($level) || ! in_array($level, $known, true)) {
                $violations->add('enforced_levels', 'must contain only global, account, client, and actor');

                return ['global'];
            }
        }

        if (! in_array('global', $value, true) || count(array_unique($value)) !== count($value)) {
            $violations->add('enforced_levels', 'must contain global and no duplicates');
        }

        return $value;
    }
}
