<?php

declare(strict_types=1);

namespace CoreX\Observability\Internal;

use InvalidArgumentException;

/**
 * Closed metric dimensions. Account, workspace, actor, correlation, causation
 * and operation ids are forbidden labels.
 */
final readonly class MetricAttributes
{
    /** @var list<string> */
    public const ALLOWED = [
        'package',
        'operation',
        'outcome',
        'category',
        'retry_bucket',
    ];

    /** @var list<string> */
    public const RETRY_BUCKETS = ['0', '1', '2-3', '4+'];

    /** @var array<string, string> */
    private array $values;

    /**
     * @param  array<string, string>  $values
     */
    public function __construct(array $values)
    {
        foreach ($values as $key => $value) {
            if (! in_array($key, self::ALLOWED, true)) {
                throw new InvalidArgumentException("Metric attribute [{$key}] is not allowed.");
            }

            if (! self::valueAllowed(key: $key, value: $value)) {
                throw new InvalidArgumentException("Metric attribute [{$key}] value is outside the bounded set.");
            }
        }

        $this->values = $values;
    }

    public static function retryBucket(int $attempt): string
    {
        if ($attempt < 0) {
            throw new InvalidArgumentException('Attempt cannot be negative.');
        }

        return match (true) {
            $attempt === 0 => '0',
            $attempt === 1 => '1',
            $attempt <= 3 => '2-3',
            default => '4+',
        };
    }

    /**
     * @return array<string, string>
     */
    public function all(): array
    {
        return $this->values;
    }

    private static function valueAllowed(string $key, string $value): bool
    {
        return match ($key) {
            'package', 'operation' => preg_match('/\\A[a-z][a-z0-9]*(?:\\.[a-z0-9]+)*\\z/D', $value) === 1 && strlen($value) <= 128,
            'outcome' => in_array($value, ['succeeded', 'cancelled', 'skipped', 'failed'], true),
            'category' => in_array($value, [
                'authentication', 'authorization', 'validation', 'conflict', 'not_found',
                'rate_limit', 'unsupported', 'unavailable', 'internal',
            ], true),
            'retry_bucket' => in_array($value, self::RETRY_BUCKETS, true),
            default => false,
        };
    }
}
