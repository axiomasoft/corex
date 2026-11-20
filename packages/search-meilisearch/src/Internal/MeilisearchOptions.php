<?php

declare(strict_types=1);

namespace CoreX\SearchMeilisearch\Internal;

use Packman\Attributes\Internal;
use Packman\Config\Laravel\ConfigurationValidator;

/**
 * The host endpoint is checked when the Meilisearch driver is created.
 *
 * @internal Package composition type: read only by the provider.
 */
#[Internal]
final readonly class MeilisearchOptions
{
    public function __construct(public ?string $host) {}

    /** @param  array<array-key, mixed>  $config */
    public static function fromArray(array $config): self
    {
        $violations = new ConfigurationValidator;
        $host = $config['host'] ?? null;

        if ($host !== null && ! is_string($host)) {
            $violations->add(path: 'host', message: 'must be null or a string');
            $host = null;
        }

        $violations->failIfAny();

        return new self(host: $host);
    }
}
