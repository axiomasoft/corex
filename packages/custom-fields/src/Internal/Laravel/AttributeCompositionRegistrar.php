<?php

declare(strict_types=1);

namespace CoreX\CustomFields\Internal\Laravel;

use CoreX\CustomFields\Data\AttributeTables;
use CoreX\CustomFields\Internal\CustomFieldsOptions;
use CoreX\Modules\Contracts\PackageRegistrar;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Foundation\Application;
use Override;

/** @internal Builds validated package configuration once per application. */
final class AttributeCompositionRegistrar implements PackageRegistrar
{
    #[Override]
    public function register(Application $app): void
    {
        $app->singleton(CustomFieldsOptions::class, static fn (Application $app): CustomFieldsOptions => CustomFieldsOptions::fromArray(
            (array) $app->make(Repository::class)->get('corex-custom-fields', []),
        ));
        // The literal fallbacks are the shipped defaults that table-write-ownership-gate resolves statically.
        $app->singleton(AttributeTables::class, static fn (Application $app): AttributeTables => new AttributeTables(
            documents: $app->make(CustomFieldsOptions::class)->tables['documents'] ?? 'sys_attribute_documents',
            writeTokens: $app->make(CustomFieldsOptions::class)->tables['write_tokens'] ?? 'sys_attribute_write_tokens',
            eavValues: $app->make(CustomFieldsOptions::class)->tables['eav_values'] ?? 'sys_attribute_eav_values',
            eavRecords: $app->make(CustomFieldsOptions::class)->tables['eav_records'] ?? 'sys_attribute_eav_records',
            schemaOperations: $app->make(CustomFieldsOptions::class)->tables['schema_operations'] ?? 'sys_attribute_schema_operations',
        ));
        $app->singleton(AttributeComposition::class, static fn (Application $app): AttributeComposition => $app->make(CustomFieldsOptions::class)->composition);
    }

    #[Override]
    public function boot(Application $app): void
    {
        $app->make(CustomFieldsOptions::class);
    }
}
