<?php

declare(strict_types=1);

namespace CoreX\CustomFields\Internal;

use CoreX\CustomFields\Contracts\AttributeFilter;
use CoreX\CustomFields\Contracts\AttributeStore;
use CoreX\CustomFields\Contracts\FieldCatalogue;
use CoreX\CustomFields\Contracts\SchemaOperations;
use CoreX\CustomFields\Data\AttributeWriteResult;
use CoreX\CustomFields\Data\DriftReport;
use CoreX\CustomFields\Data\FieldSelection;
use CoreX\CustomFields\Data\FieldSnapshot;
use CoreX\CustomFields\Data\FilterNode;
use CoreX\CustomFields\Data\FilterPlan;
use CoreX\CustomFields\Data\OperationRef;
use CoreX\CustomFields\Data\RecordSelection;
use CoreX\CustomFields\Data\SchemaChange;
use CoreX\CustomFields\Data\SchemaOperationId;
use CoreX\CustomFields\Data\SchemaReceipt;
use CoreX\CustomFields\Data\ValueBag;
use CoreX\CustomFields\Data\ValueBatch;
use CoreX\CustomFields\Data\ValuePatch;
use CoreX\Data\Data\DataScope;
use CoreX\Data\Data\RecordRef;
use CoreX\Data\Data\WriteToken;

final class UnavailableAttributes implements AttributeFilter, AttributeStore, FieldCatalogue, SchemaOperations
{
    public function snapshot(DataScope $scope, string $entity): FieldSnapshot
    {
        throw new UnsupportedAttributeCapability;
    }

    public function read(DataScope $scope, RecordRef $record, FieldSelection $fields): ValueBag
    {
        throw new UnsupportedAttributeCapability;
    }

    public function write(DataScope $scope, RecordRef $record, ValuePatch $patch, WriteToken $token): AttributeWriteResult
    {
        throw new UnsupportedAttributeCapability;
    }

    public function loadMany(DataScope $scope, RecordSelection $records, FieldSelection $fields): ValueBatch
    {
        throw new UnsupportedAttributeCapability;
    }

    public function compile(DataScope $scope, FieldSnapshot $schema, FilterNode $filter): FilterPlan
    {
        throw new UnsupportedAttributeCapability;
    }

    public function request(DataScope $scope, SchemaChange $change, SchemaOperationId $key): SchemaReceipt
    {
        throw new UnsupportedAttributeCapability;
    }

    public function inspect(DataScope $scope, OperationRef $operation): SchemaReceipt
    {
        throw new UnsupportedAttributeCapability;
    }

    public function verify(DataScope $scope, string $entity): DriftReport
    {
        throw new UnsupportedAttributeCapability;
    }
}
