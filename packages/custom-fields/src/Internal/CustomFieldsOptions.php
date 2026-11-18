<?php

declare(strict_types=1);

namespace CoreX\CustomFields\Internal;

use CoreX\CustomFields\Internal\Laravel\AttributeComposition;
use Packman\Attributes\Internal;

/**
 * Shape of `corex-custom-fields`.
 *
 * @internal Package composition type: read only by the provider and registrars.
 */
#[Internal]
final readonly class CustomFieldsOptions
{
    public function __construct(
        public AttributeComposition $composition,
    ) {}

    /** @param  array<array-key, mixed>  $config */
    public static function fromArray(array $config): self
    {
        return new self(AttributeComposition::fromArray($config));
    }
}
