<?php

declare(strict_types=1);

namespace CoreX\Api\Contracts;

use CoreX\Api\Data\BulkOperation;
use CoreX\Api\Data\BulkOperationDefinition;
use CoreX\Support\Data\DataScope;

interface BulkOperationStore
{
    public function submit(BulkOperationDefinition $definition): BulkOperation;

    public function find(DataScope $scope, string $id): ?BulkOperation;

    public function claim(DataScope $scope, string $worker): ?BulkOperation;

    public function save(BulkOperation $operation, string $leaseOwner, int $revision): bool;

    public function cancel(DataScope $scope, string $id): BulkOperation;
}
