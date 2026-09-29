<?php

declare(strict_types=1);

namespace CoreX\CustomFields\Tests\Unit;

use CoreX\CustomFields\AttributesServiceProvider;
use CoreX\CustomFields\Data\AttributeTables;
use CoreX\CustomFields\Exceptions\InvalidAttributeTables;
use Orchestra\Testbench\TestCase;
use Override;

final class AttributeTablesTest extends TestCase
{
    #[Override]
    protected function getPackageProviders($app): array
    {
        return [AttributesServiceProvider::class];
    }

    public function test_it_accepts_the_shipped_default_table_names(): void
    {
        $tables = new AttributeTables(
            documents: 'sys_attribute_documents',
            writeTokens: 'sys_attribute_write_tokens',
            eavValues: 'sys_attribute_eav_values',
            eavRecords: 'sys_attribute_eav_records',
            schemaOperations: 'sys_attribute_schema_operations',
        );

        expect($tables->documents)->toBe('sys_attribute_documents')
            ->and($tables->schemaOperations)->toBe('sys_attribute_schema_operations');
    }

    public function test_it_rejects_an_invalid_table_identifier_at_construction_time(): void
    {
        expect(fn (): AttributeTables => new AttributeTables(
            documents: 'sys_attribute_documents',
            writeTokens: 'sys_attribute_write_tokens',
            eavValues: 'sys_attribute_eav_values',
            eavRecords: 'sys_attribute_eav_records',
            schemaOperations: 'INVALID',
        ))->toThrow(InvalidAttributeTables::class);
    }

    public function test_it_rejects_duplicate_configured_table_names(): void
    {
        expect(fn (): AttributeTables => new AttributeTables(
            documents: 'sys_attribute_documents',
            writeTokens: 'sys_attribute_documents',
            eavValues: 'sys_attribute_eav_values',
            eavRecords: 'sys_attribute_eav_records',
            schemaOperations: 'sys_attribute_schema_operations',
        ))->toThrow(InvalidAttributeTables::class);
    }

    public function test_it_rejects_invalid_table_names_loaded_from_provider_configuration(): void
    {
        $this->app['config']->set('corex-attributes.tables.documents', 'Bad-Name');

        expect(fn (): AttributeTables => $this->app->make(AttributeTables::class))
            ->toThrow(InvalidAttributeTables::class);
    }
}
