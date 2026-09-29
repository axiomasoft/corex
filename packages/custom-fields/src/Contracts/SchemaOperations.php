<?php

declare(strict_types=1);

namespace CoreX\CustomFields\Contracts;

use CoreX\CustomFields\Data\DriftReport;
use CoreX\CustomFields\Data\OperationRef;
use CoreX\CustomFields\Data\SchemaChange;
use CoreX\CustomFields\Data\SchemaOperationId;
use CoreX\CustomFields\Data\SchemaReceipt;
use CoreX\Data\DataScope;

interface SchemaOperations
{
    public function request(DataScope $scope, SchemaChange $change, SchemaOperationId $key): SchemaReceipt;

    public function inspect(DataScope $scope, OperationRef $operation): SchemaReceipt;

    public function verify(DataScope $scope, string $entity): DriftReport;
}
