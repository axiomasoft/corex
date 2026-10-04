<?php

declare(strict_types=1);

namespace CoreX\SearchMeilisearch\Internal;

use CoreX\Config\Data\SecretRef;
use CoreX\Config\Laravel\ConfigurationValidator;

/**
 * Shape of `corex-search-meilisearch`. Presence of host, index and key is a runtime
 * requirement of the "meilisearch" driver, checked when the driver is created.
 *
 * @internal Package composition type: read only by the provider.
 */
final readonly class MeilisearchOptions
{
    public function __construct(
        public ?string $host,
        public ?string $indexUid,
        public ?SecretRef $key,
    ) {}

    /** @param  array<array-key, mixed>  $config */
    public static function fromArray(array $config): self
    {
        $violations = new ConfigurationValidator;

        $host = self::nullableString($violations, 'host', $config['host'] ?? null);
        $indexUid = self::nullableString($violations, 'index_uid', $config['index_uid'] ?? null);
        $key = self::nullableString($violations, 'key', $config['key'] ?? null);

        $violations->failIfAny();

        return new self($host, $indexUid, $key === null || $key === '' ? null : new SecretRef($key));
    }

    private static function nullableString(ConfigurationValidator $violations, string $path, mixed $value): ?string
    {
        if ($value === null || is_string($value)) {
            return $value;
        }

        $violations->add($path, 'must be null or a string');

        return null;
    }
}
