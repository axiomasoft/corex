<?php

declare(strict_types=1);

namespace CoreX\CustomFields\Internal;

use Packman\Attributes\Internal;
use Packman\Config\Laravel\ConfigurationValidator;

#[Internal]
final readonly class CustomFieldsOptions
{
    private function __construct(public bool $ddl) {}

    /** @param array<array-key,mixed> $config */
    public static function fromArray(array $config): self
    {
        $validator = new ConfigurationValidator;
        $validator->rejectUnknownKeys($config, ['ddl'], 'corex-custom-fields');
        $enabled = array_key_exists('ddl', $config) ? $config['ddl'] : true;

        if (! is_bool($enabled)) {
            $validator->add('corex-custom-fields.ddl', 'expected a boolean');
        }
        $validator->failIfAny();

        return new self($enabled === true);
    }
}
