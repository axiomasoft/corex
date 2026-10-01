<?php

declare(strict_types=1);

namespace CoreX\Operations\Bulk\Contracts;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use CoreX\Data\Data\DataScope;
use CoreX\Operations\Bulk\Data\BulkOperation;
use CoreX\Operations\Bulk\Data\BulkOperationDefinition;

#[Spi(kind: SpiKind::Implement, since: '1.0')]
interface BulkOperationStore
{
    public function submit(BulkOperationDefinition $definition): BulkOperation;

    public function find(DataScope $scope, string $id): ?BulkOperation;

    public function claim(DataScope $scope, string $worker): ?BulkOperation;

    public function save(BulkOperation $operation, string $leaseOwner, int $revision): bool;

    public function cancel(DataScope $scope, string $id): BulkOperation;
}
