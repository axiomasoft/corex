<?php

declare(strict_types=1);

namespace CoreX\CustomFields\Internal\Laravel;

use CoreX\CustomFields\Data\AttributeTables;
use CoreX\CustomFields\Internal\CustomFieldsOptions;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Foundation\Application;

/** @internal Builds validated package configuration once per application. */
final class AttributeCompositionRegistrar
{
    public function register(Application $app): void
    {
        $app->singleton(CustomFieldsOptions::class, static fn (Application $app): CustomFieldsOptions => CustomFieldsOptions::fromArray(
            (array) $app->make(Repository::class)->get('corex-custom-fields', []),
        ));
        $app->singleton(AttributeTables::class, static function (Application $app): AttributeTables {
            $tables = $app->make(CustomFieldsOptions::class)->tables;

            // The literal fallbacks are the shipped defaults that table-write-ownership-gate resolves statically.
            return new AttributeTables(
                documents: $tables['documents'] ?? 'sys_attribute_documents',
                writeTokens: $tables['write_tokens'] ?? 'sys_attribute_write_tokens',
                eavValues: $tables['eav_values'] ?? 'sys_attribute_eav_values',
                eavRecords: $tables['eav_records'] ?? 'sys_attribute_eav_records',
                schemaOperations: $tables['schema_operations'] ?? 'sys_attribute_schema_operations',
            );
        });
        $app->singleton(AttributeComposition::class, static fn (Application $app): AttributeComposition => $app->make(CustomFieldsOptions::class)->composition);
    }
}
