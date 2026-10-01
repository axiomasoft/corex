<?php

declare(strict_types=1);

namespace CoreX\Operations\Bulk\Contracts;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use CoreX\Operations\Bulk\Data\BulkOperation;
use CoreX\Operations\Bulk\Data\BulkTransferSession;

#[Spi(kind: SpiKind::Implement, since: '1.0')]
interface BulkTransferFactory
{
    public function create(BulkOperation $operation): BulkTransferSession;
}
