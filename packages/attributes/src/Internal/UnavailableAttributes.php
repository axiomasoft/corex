<?php

declare(strict_types=1);

namespace CoreX\Attributes\Internal;

use CoreX\Attributes\Contracts\AttributeFilter;
use CoreX\Attributes\Contracts\AttributeStore;
use CoreX\Attributes\Contracts\FieldCatalogue;
use CoreX\Attributes\Contracts\SchemaOperations;
use CoreX\Attributes\Support\FieldSelection;
use CoreX\Attributes\Support\FieldSnapshot;
use CoreX\Attributes\Support\FilterNode;
use CoreX\Attributes\Support\FilterPlan;
use CoreX\Attributes\Support\RecordSelection;
use CoreX\Attributes\Support\Schema\DriftReport;
use CoreX\Attributes\Support\Schema\OperationKey;
use CoreX\Attributes\Support\Schema\OperationRef;
use CoreX\Attributes\Support\Schema\SchemaChange;
use CoreX\Attributes\Support\Schema\SchemaReceipt;
use CoreX\Attributes\Support\ValueBag;
use CoreX\Attributes\Support\ValueBatch;
use CoreX\Attributes\Support\ValuePatch;
use CoreX\Attributes\Support\WriteResult;
use CoreX\Support\Data\DataScope;
use CoreX\Support\Data\RecordRef;
use CoreX\Support\Data\WriteToken;

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

    public function write(DataScope $scope, RecordRef $record, ValuePatch $patch, WriteToken $token): WriteResult
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

    public function request(DataScope $scope, SchemaChange $change, OperationKey $key): SchemaReceipt
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
