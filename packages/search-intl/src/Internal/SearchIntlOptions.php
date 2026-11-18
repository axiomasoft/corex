<?php

declare(strict_types=1);

namespace CoreX\SearchIntl\Internal;

use Packman\Attributes\Internal;
use Packman\Config\Laravel\ConfigurationValidator;

/** @internal Package composition type: read only by the provider. */
#[Internal]
final readonly class SearchIntlOptions
{
    public function __construct(
        public bool $enabled,
    ) {}

    /** @param  array<array-key, mixed>  $config */
    public static function fromArray(array $config): self
    {
        $violations = new ConfigurationValidator;

        $enabled = $config['enabled'] ?? false;

        if (! is_bool($enabled)) {
            $violations->add(path: 'enabled', message: 'must be a boolean');
            $enabled = false;
        }

        $violations->failIfAny();

        return new self($enabled);
    }
}
