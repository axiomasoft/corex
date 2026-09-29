<?php

declare(strict_types=1);

namespace CoreX\CustomFields\Contracts;

use CoreX\CustomFields\Support\Schema\DriftReport;
use CoreX\CustomFields\Support\Schema\OperationRef;
use CoreX\CustomFields\Support\Schema\SchemaChange;
use CoreX\CustomFields\Support\Schema\SchemaOperationId;
use CoreX\CustomFields\Support\Schema\SchemaReceipt;
use CoreX\Data\DataScope;

interface SchemaOperations
{
    public function request(DataScope $scope, SchemaChange $change, SchemaOperationId $key): SchemaReceipt;

    public function inspect(DataScope $scope, OperationRef $operation): SchemaReceipt;

    public function verify(DataScope $scope, string $entity): DriftReport;
}
