<?php

declare(strict_types=1);

namespace CoreX\Modules\Internal\Support;

use CoreX\Config\Laravel\ConfigurationValidator;

/** @internal Package composition type. */
final readonly class ModulesOptions
{
    public function __construct(
        public ?string $connection,
        public ?string $cacheStore,
        public int $cacheTtl,
        public string $cachePrefix,
    ) {}

    /** @param  array<array-key, mixed>  $config */
    public static function fromArray(array $config): self
    {
        $violations = new ConfigurationValidator;

        $connection = self::nullableNonEmptyString($config['connection'] ?? null);

        $cache = is_array($config['cache'] ?? null) ? $config['cache'] : [];
        $cacheStore = self::nullableNonEmptyString($cache['store'] ?? null);
        $cacheTtl = self::positiveInt(violations: $violations, path: 'cache.ttl', value: $cache['ttl'] ?? 3600);
        $cachePrefix = self::nonEmpty(violations: $violations, path: 'cache.prefix', value: $cache['prefix'] ?? 'corex.modules');

        $violations->failIfAny();

        return new self(
            connection: $connection,
            cacheStore: $cacheStore,
            cacheTtl: $cacheTtl,
            cachePrefix: $cachePrefix,
        );
    }

    private static function nonEmpty(ConfigurationValidator $violations, string $path, mixed $value): string
    {
        if (is_string($value) && $value !== '') {
            return $value;
        }

        $violations->add(path: $path, message: 'must be a non-empty string');

        return 'invalid';
    }

    private static function nullableNonEmptyString(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
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
