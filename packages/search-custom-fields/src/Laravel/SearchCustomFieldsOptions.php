<?php

declare(strict_types=1);

namespace CoreX\SearchCustomFields\Laravel;

use InvalidArgumentException;
use Packman\Attributes\Internal;

#[Internal]
final readonly class SearchCustomFieldsOptions
{
    public function __construct(public bool $enabled) {}

    /** @param array<array-key,mixed> $config */
    public static function fromArray(array $config): self
    {
        $enabled = array_key_exists('enabled', $config) ? $config['enabled'] : false;

        if (! is_bool($enabled)) {
            throw new InvalidArgumentException('Search Custom Fields enabled must be boolean.');
        }

        return new self($enabled);
    }
}
