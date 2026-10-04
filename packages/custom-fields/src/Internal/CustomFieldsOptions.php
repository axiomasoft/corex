<?php

declare(strict_types=1);

namespace CoreX\CustomFields\Internal;

use CoreX\Config\Laravel\ConfigurationValidator;
use CoreX\CustomFields\Internal\Laravel\AttributeComposition;

/**
 * Shape of `corex-custom-fields`. Table identifiers are validated when `AttributeTables` is built.
 *
 * @internal Package composition type: read only by the provider and registrars.
 */
final readonly class CustomFieldsOptions
{
    private const array TABLE_DEFAULTS = [
        'documents' => 'sys_attribute_documents',
        'write_tokens' => 'sys_attribute_write_tokens',
        'eav_values' => 'sys_attribute_eav_values',
        'eav_records' => 'sys_attribute_eav_records',
        'schema_operations' => 'sys_attribute_schema_operations',
    ];

    /** @param  array<string, string>  $tables keyed by the table-map names of the config file */
    public function __construct(
        public AttributeComposition $composition,
        public array $tables,
    ) {}

    /** @param  array<array-key, mixed>  $config */
    public static function fromArray(array $config): self
    {
        $violations = new ConfigurationValidator;
        $configured = is_array($config['tables'] ?? null) ? $config['tables'] : [];

        $tables = [];
        foreach (self::TABLE_DEFAULTS as $name => $default) {
            $value = $configured[$name] ?? $default;

            if (! is_string($value)) {
                $violations->add('tables.'.$name, 'must be a string');
                $value = $default;
            }

            $tables[$name] = $value;
        }

        $violations->failIfAny();

        return new self(AttributeComposition::fromArray($config), $tables);
    }
}
