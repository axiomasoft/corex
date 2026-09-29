<?php

declare(strict_types=1);

namespace CoreX\CustomFields\Internal;

use CoreX\CustomFields\Contracts\AttributeFilter;
use CoreX\CustomFields\Contracts\AttributeStore;
use CoreX\CustomFields\Contracts\FieldCatalogue;
use CoreX\CustomFields\Contracts\SchemaOperations;
use CoreX\CustomFields\Support\AttributeWriteResult;
use CoreX\CustomFields\Support\FieldSelection;
use CoreX\CustomFields\Support\FieldSnapshot;
use CoreX\CustomFields\Support\FilterNode;
use CoreX\CustomFields\Support\FilterPlan;
use CoreX\CustomFields\Support\RecordSelection;
use CoreX\CustomFields\Support\Schema\DriftReport;
use CoreX\CustomFields\Support\Schema\OperationRef;
use CoreX\CustomFields\Support\Schema\SchemaChange;
use CoreX\CustomFields\Support\Schema\SchemaOperationId;
use CoreX\CustomFields\Support\Schema\SchemaReceipt;
use CoreX\CustomFields\Support\ValueBag;
use CoreX\CustomFields\Support\ValueBatch;
use CoreX\CustomFields\Support\ValuePatch;
use CoreX\Data\DataScope;
use CoreX\Data\RecordRef;
use CoreX\Data\WriteToken;

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
