<?php

declare(strict_types=1);

namespace CoreX\Operations\Bulk\Contracts;

use CoreX\Data\Data\DataScope;
use CoreX\Operations\Bulk\Data\BulkOperationDefinition;
use CoreX\Operations\Invocation\Data\OperationInvocation;
use Packman\Attributes\Spi;
use Packman\Attributes\SpiKind;

#[Spi(kind: SpiKind::Implement, since: '2.0.0')]
interface BulkDraftFactory
{
    public function create(OperationInvocation $invocation, DataScope $scope): BulkOperationDefinition;
}
