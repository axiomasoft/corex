<?php

declare(strict_types=1);

namespace CoreX\CustomFields\Internal\Laravel;

use CoreX\CustomFields\Data\AttributeTables;
use CoreX\CustomFields\Internal\CustomFieldsOptions;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Foundation\Application;
use Override;
use Packman\Attributes\Internal;
use Packman\Foundation\Contracts\PackageRegistrar;

/** @internal Builds validated package configuration once per application. */
#[Internal]
final class AttributeCompositionRegistrar implements PackageRegistrar
{
    #[Override]
    public function register(Application $app): void
    {
        $app->singleton(CustomFieldsOptions::class, static fn (Application $app): CustomFieldsOptions => CustomFieldsOptions::fromArray(
            (array) $app->make(Repository::class)->get('corex-custom-fields', []),
        ));
        // Package-owned tables keep the schema independent of host configuration.
        $app->singleton(AttributeTables::class, static fn (): AttributeTables => new AttributeTables(
            documents: 'sys_attribute_documents',
            writeTokens: 'sys_attribute_write_tokens',
            eavValues: 'sys_attribute_eav_values',
            eavRecords: 'sys_attribute_eav_records',
            schemaOperations: 'sys_attribute_schema_operations',
        ));
        $app->singleton(AttributeComposition::class, static fn (Application $app): AttributeComposition => $app->make(CustomFieldsOptions::class)->composition);
    }

    #[Override]
    public function boot(Application $app): void
    {
        $app->make(CustomFieldsOptions::class);
    }
}
