<?php

declare(strict_types=1);

namespace CoreX\ImportExport\Internal;

use CoreX\Config\Laravel\ConfigurationValidator;

/** @internal Package composition type: read only by the provider. */
final readonly class ImportExportOptions
{
    public function __construct(
        public int $limitsMaxRecordBytes,
        public int $limitsMaxFields,
        public int $limitsBatchRows,
        public int $limitsMaxMemoryMiB,
    ) {}

    /** @param  array<array-key, mixed>  $config */
    public static function fromArray(array $config): self
    {
        $violations = new ConfigurationValidator;
        $limits = is_array($config['limits'] ?? null) ? $config['limits'] : [];

        $maxRecordBytes = self::positiveInt(violations: $violations, path: 'limits.max_record_bytes', value: $limits['max_record_bytes'] ?? 1_048_576);
        $maxFields = self::positiveInt(violations: $violations, path: 'limits.max_fields', value: $limits['max_fields'] ?? 256);
        $batchRows = self::positiveInt(violations: $violations, path: 'limits.batch_rows', value: $limits['batch_rows'] ?? 500);
        $maxMemoryMiB = self::positiveInt(violations: $violations, path: 'limits.max_memory_mib', value: $limits['max_memory_mib'] ?? 128);

        if ($maxMemoryMiB > 1024) {
            $violations->add(path: 'limits.max_memory_mib', message: 'must not exceed 1024 MiB');
        }

        $violations->failIfAny();

        return new self(limitsMaxRecordBytes: $maxRecordBytes, limitsMaxFields: $maxFields, limitsBatchRows: $batchRows, limitsMaxMemoryMiB: $maxMemoryMiB);
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
