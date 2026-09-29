<?php

declare(strict_types=1);

namespace CoreX\CustomFields\Internal\Laravel;

use CoreX\CustomFields\Data\AttributeTables;
use Illuminate\Contracts\Foundation\Application;

/** @internal Builds validated package configuration once per application. */
final class AttributeCompositionRegistrar
{
    public function register(Application $app): void
    {
        $app->singleton(AttributeTables::class, static function (Application $app): AttributeTables {
            /** @var array<string, mixed> $tables */
            $tables = (array) $app['config']->get('corex-custom-fields.tables', []);

            return new AttributeTables(
                documents: (string) ($tables['documents'] ?? 'sys_attribute_documents'),
                writeTokens: (string) ($tables['write_tokens'] ?? 'sys_attribute_write_tokens'),
                eavValues: (string) ($tables['eav_values'] ?? 'sys_attribute_eav_values'),
                eavRecords: (string) ($tables['eav_records'] ?? 'sys_attribute_eav_records'),
                schemaOperations: (string) ($tables['schema_operations'] ?? 'sys_attribute_schema_operations'),
            );
        });
        $app->singleton(AttributeComposition::class, static function (Application $app): AttributeComposition {
            /** @var array<string, mixed> $config */
            $config = (array) $app['config']->get('corex-custom-fields', []);

            return AttributeComposition::fromArray($config);
        });
    }
}
