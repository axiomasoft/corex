<?php

declare(strict_types=1);

namespace CoreX\Modules\Support;

use CoreX\Support\ViolationList;

final readonly class ModulesOptions
{
    public function __construct(
        public ?string $connection,
        public ?string $cacheStore,
        public int $cacheTtl,
        public string $cachePrefix,
    ) {}

    /** @param  array<string, mixed>  $config */
    public static function fromArray(array $config): self
    {
        $violations = new ViolationList;

        $connection = self::nullableNonEmptyString($config['connection'] ?? null);

        $cache = is_array($config['cache'] ?? null) ? $config['cache'] : [];
        $cacheStore = self::nullableNonEmptyString($cache['store'] ?? null);
        $cacheTtl = self::positiveInt($violations, 'cache.ttl', $cache['ttl'] ?? 3600);
        $cachePrefix = self::nonEmpty($violations, 'cache.prefix', $cache['prefix'] ?? 'corex-modules');

        $violations->failIfAny();

        return new self(
            connection: $connection,
            cacheStore: $cacheStore,
            cacheTtl: $cacheTtl,
            cachePrefix: $cachePrefix,
        );
    }

    private static function nonEmpty(ViolationList $violations, string $path, mixed $value): string
    {
        if (is_string($value) && $value !== '') {
            return $value;
        }

        $violations->add($path, 'must be a non-empty string');

        return 'invalid';
    }

    private static function nullableNonEmptyString(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
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
