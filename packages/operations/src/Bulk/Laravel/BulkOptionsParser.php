<?php

declare(strict_types=1);

namespace CoreX\Operations\Bulk\Laravel;

use CoreX\Operations\Bulk\Data\BulkOptions;
use Packman\Attributes\Spi;
use Packman\Attributes\SpiKind;
use Packman\Config\Laravel\ConfigurationValidator;

/** Immutable durable-bulk activation policy read only by the package registrar. */
#[Spi(kind: SpiKind::Call, since: '1.0')]
final class BulkOptionsParser
{
    /** @param array<array-key, mixed> $config */
    public static function fromArray(array $config): BulkOptions
    {
        $violations = new ConfigurationValidator;
        $enabled = self::bool(violations: $violations, path: 'enabled', value: $config['enabled'] ?? true);
        $worker = self::worker(violations: $violations, value: $config['worker'] ?? 'api-bulk');
        $lease = self::positiveInt(violations: $violations, path: 'lease_seconds', value: $config['lease_seconds'] ?? 30);
        $retry = self::positiveInt(violations: $violations, path: 'retry_seconds', value: $config['retry_seconds'] ?? 5);
        $resultRetention = self::positiveInt(violations: $violations, path: 'result_retention_seconds', value: $config['result_retention_seconds'] ?? 86_400);
        $metadataRetention = self::positiveInt(violations: $violations, path: 'metadata_retention_seconds', value: $config['metadata_retention_seconds'] ?? 2_592_000);
        $idempotencyRetention = self::positiveInt(violations: $violations, path: 'idempotency_retention_seconds', value: $config['idempotency_retention_seconds'] ?? 86_400);

        if ($resultRetention > $metadataRetention) {
            $violations->add(path: 'result_retention_seconds', message: 'must not exceed metadata_retention_seconds');
        }

        if ($resultRetention > $idempotencyRetention) {
            $violations->add(path: 'result_retention_seconds', message: 'must not exceed idempotency_retention_seconds');
        }

        if ($metadataRetention < $idempotencyRetention) {
            $violations->add(path: 'metadata_retention_seconds', message: 'must cover idempotency_retention_seconds');
        }

        $rows = self::positiveInt(violations: $violations, path: 'row_limit', value: $config['row_limit'] ?? 500);
        $seconds = self::positiveInt(violations: $violations, path: 'time_limit_seconds', value: $config['time_limit_seconds'] ?? min(20, max(1, $lease - 1)));
        $attempts = self::positiveInt(violations: $violations, path: 'max_recovery_attempts', value: $config['max_recovery_attempts'] ?? 5);

        if ($rows > 500 || $seconds > 30 || $seconds >= $lease || $attempts > 100) {
            $violations->add(path: 'bulk_limits', message: 'requires at most 500 rows, 30 seconds shorter than the lease, and 100 recovery attempts');
        }

        $violations->failIfAny();

        return new BulkOptions(
            enabled: $enabled,
            worker: $worker,
            leaseSeconds: $lease,
            retrySeconds: $retry,
            resultRetentionSeconds: $resultRetention,
            metadataRetentionSeconds: $metadataRetention,
            idempotencyRetentionSeconds: $idempotencyRetention,
            rowLimit: $rows,
            timeLimitSeconds: $seconds,
            maxRecoveryAttempts: $attempts,
        );
    }

    private static function bool(ConfigurationValidator $violations, string $path, mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        $violations->add(path: $path, message: 'must be a boolean');

        return false;
    }

    private static function worker(ConfigurationValidator $violations, mixed $value): string
    {
        if (is_string($value) && preg_match('/\A[a-z][a-z0-9._-]{0,63}\z/D', $value) === 1) {
            return $value;
        }

        $violations->add(path: 'worker', message: 'must be a safe identifier of at most 64 bytes');

        return 'invalid';
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
