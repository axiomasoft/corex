<?php

declare(strict_types=1);

namespace CoreX\Api\Data;

use CoreX\Support\ViolationList;

/** Immutable durable-bulk activation policy read only by the package registrar. */
final readonly class BulkOptions
{
    public function __construct(
        public bool $enabled,
        public string $worker,
        public int $leaseSeconds,
        public int $retrySeconds,
        public int $resultRetentionSeconds,
        public int $metadataRetentionSeconds,
        public int $idempotencyRetentionSeconds,
    ) {}

    /** @param array<string, mixed> $config */
    public static function fromArray(array $config): self
    {
        $violations = new ViolationList;
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

        return new self(
            enabled: $enabled,
            worker: $worker,
            leaseSeconds: $lease,
            retrySeconds: $retry,
            resultRetentionSeconds: $resultRetention,
            metadataRetentionSeconds: $metadataRetention,
            idempotencyRetentionSeconds: $idempotencyRetention,
        );
    }

    private static function bool(ViolationList $violations, string $path, mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        $violations->add($path, 'must be a boolean');

        return false;
    }

    private static function worker(ViolationList $violations, mixed $value): string
    {
        if (is_string($value) && preg_match('/\A[a-z][a-z0-9._-]{0,63}\z/D', $value) === 1) {
            return $value;
        }

        $violations->add('worker', 'must be a safe identifier of at most 64 bytes');

        return 'invalid';
    }

    private static function positiveInt(ViolationList $violations, string $path, mixed $value): int
    {
        if (is_int($value) && $value > 0) {
            return $value;
        }

        $violations->add($path, 'must be a positive integer');

        return 1;
    }
}
