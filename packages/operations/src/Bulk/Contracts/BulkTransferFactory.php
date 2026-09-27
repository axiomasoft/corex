<?php

declare(strict_types=1);

namespace CoreX\Api\Contracts;

use CoreX\Api\Data\BulkOperation;
use CoreX\Api\Data\BulkTransferSession;

interface BulkTransferFactory
{
    public function create(BulkOperation $operation): BulkTransferSession;
}
