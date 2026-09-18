<?php

declare(strict_types=1);

namespace CoreX\Attributes\Tests\Support;

use CoreX\Attributes\Contracts\FieldCatalogue;
use CoreX\Attributes\Data\AttributeTables;
use CoreX\Attributes\Support\FieldSnapshot;
use CoreX\Support\Data\DataScope;

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

        return new class($snapshot) implements FieldCatalogue
        {
            public function __construct(private FieldSnapshot $snapshot) {}

            public function snapshot(DataScope $scope, string $entity): FieldSnapshot
            {
                return $this->snapshot;
            }
        };
    }
}
