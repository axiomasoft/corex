<?php

declare(strict_types=1);

namespace CoreX\CustomFields\Tests\Support;

use CoreX\CustomFields\Contracts\FieldCatalogue;
use CoreX\CustomFields\Data\AttributeTables;
use CoreX\CustomFields\Data\FieldSnapshot;
use CoreX\Data\Data\DataScope;

final class PgTestSupport
{
    public static function tables(): AttributeTables
    {
        return new AttributeTables(
            documents: 'sys_attribute_documents',
            writeTokens: 'sys_attribute_write_tokens',
            eavValues: 'sys_attribute_eav_values',
            eavRecords: 'sys_attribute_eav_records',
            schemaOperations: 'sys_attribute_schema_operations',
        );
    }

    /**
     * @param  list<array{code: string, type: string, required?: bool}>  $fields
     */
    public static function catalogue(string $entity, array $fields): FieldCatalogue
    {
        $hash = str_repeat('a', 64);
        $snapshot = new FieldSnapshot($entity, $hash, $hash, $fields, []);

        return new readonly class($snapshot) implements FieldCatalogue
        {
            public function __construct(private FieldSnapshot $snapshot) {}

            public function snapshot(DataScope $scope, string $entity): FieldSnapshot
            {
                return $this->snapshot;
            }
        };
    }
}
