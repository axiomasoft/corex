<?php

declare(strict_types=1);

namespace CoreX\Attributes\Contracts;

use CoreX\Attributes\Support\Schema\DriftReport;
use CoreX\Attributes\Support\Schema\OperationKey;
use CoreX\Attributes\Support\Schema\OperationRef;
use CoreX\Attributes\Support\Schema\SchemaChange;
use CoreX\Attributes\Support\Schema\SchemaReceipt;
use CoreX\Support\Data\DataScope;

interface SchemaOperations
{
    public function request(DataScope $scope, SchemaChange $change, OperationKey $key): SchemaReceipt;

    public function inspect(DataScope $scope, OperationRef $operation): SchemaReceipt;

    public function verify(DataScope $scope, string $entity): DriftReport;
}
