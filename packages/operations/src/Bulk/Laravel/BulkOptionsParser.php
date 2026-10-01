<?php

declare(strict_types=1);

namespace CoreX\Operations\Bulk\Laravel;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use CoreX\Config\Laravel\ConfigurationValidator;
use CoreX\Operations\Bulk\Data\BulkOptions;

/** Immutable durable-bulk activation policy read only by the package registrar. */
#[Spi(kind: SpiKind::Call, since: '1.0')]
final class BulkOptionsParser
{
    /** @param array<array-key, mixed> $config */
    public static function fromArray(array $config): BulkOptions
    {
        $violations = new ConfigurationValidator;
        $enabled = self::bool($violations, 'enabled', $config['enabled'] ?? true);
        $worker = self::worker($violations, $config['worker'] ?? 'api-bulk');
        $lease = self::positiveInt($violations, 'lease_seconds', $config['lease_seconds'] ?? 30);
        $retry = self::positiveInt($violations, 'retry_seconds', $config['retry_seconds'] ?? 5);
        $resultRetention = self::positiveInt($violations, 'result_retention_seconds', $config['result_retention_seconds'] ?? 86_400);
        $metadataRetention = self::positiveInt($violations, 'metadata_retention_seconds', $config['metadata_retention_seconds'] ?? 2_592_000);
        $idempotencyRetention = self::positiveInt($violations, 'idempotency_retention_seconds', $config['idempotency_retention_seconds'] ?? 86_400);

        if ($resultRetention > $metadataRetention) {
            $violations->add('result_retention_seconds', 'must not exceed metadata_retention_seconds');
        }

        if ($resultRetention > $idempotencyRetention) {
            $violations->add('result_retention_seconds', 'must not exceed idempotency_retention_seconds');
        }

        if ($metadataRetention < $idempotencyRetention) {
            $violations->add('metadata_retention_seconds', 'must cover idempotency_retention_seconds');
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

    private static function worker(ConfigurationValidator $violations, mixed $value): string
    {
        if (is_string($value) && preg_match('/\A[a-z][a-z0-9._-]{0,63}\z/D', $value) === 1) {
            return $value;
        }

        $violations->add('worker', 'must be a safe identifier of at most 64 bytes');

        return 'invalid';
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
