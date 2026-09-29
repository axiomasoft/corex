<?php

declare(strict_types=1);

namespace CoreX\Operations\Bulk\Contracts;

use CoreX\Operations\Bulk\Data\BulkOperation;
use CoreX\Operations\Bulk\Data\BulkTransferSession;

interface BulkTransferFactory
{
    public function create(BulkOperation $operation): BulkTransferSession;
}
