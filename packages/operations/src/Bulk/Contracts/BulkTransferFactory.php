<?php

declare(strict_types=1);

namespace CoreX\Operations\Bulk\Contracts;

use CoreX\Operations\Bulk\Data\BulkOperation;
use CoreX\Operations\Bulk\Data\BulkTransferSession;
use Packman\Attributes\Spi;
use Packman\Attributes\SpiKind;

#[Spi(kind: SpiKind::Implement, since: '1.0')]
interface BulkTransferFactory
{
    public function create(BulkOperation $operation): BulkTransferSession;
}
