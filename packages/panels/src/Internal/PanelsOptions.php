<?php

declare(strict_types=1);

namespace CoreX\Panels\Internal;

use CoreX\Config\Laravel\ConfigurationValidator;

/** @internal Package composition type: read only by the provider and registrars. */
final readonly class PanelsOptions
{
    public function __construct(
        public string $providerTag,
    ) {}

    /** @param  array<array-key, mixed>  $config */
    public static function fromArray(array $config): self
    {
        $violations = new ConfigurationValidator;

        $providerTag = $config['provider_tag'] ?? 'corex.panels.providers';

        if (! is_string($providerTag) || $providerTag === '') {
            $violations->add(path: 'provider_tag', message: 'must be a non-empty string');
            $providerTag = 'corex.panels.providers';
        }

        $violations->failIfAny();

        return new self($providerTag);
    }
}
