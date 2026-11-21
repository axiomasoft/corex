<?php

declare(strict_types=1);

namespace CoreX\Foundation\Contracts;

use CoreX\Data\Data\DataScope;
use CoreX\Foundation\Data\CleanupAction;
use CoreX\Foundation\Data\CleanupReceipt;
use Packman\Attributes\Spi;
use Packman\Attributes\SpiKind;

#[Spi(kind: SpiKind::Implement, since: '2.0.0')]
interface ReferenceCleanup
{
    public function lookup(DataScope $scope, CleanupAction $action): ?CleanupReceipt;

    /** Idempotent by effectKey even after a lost acknowledgment. */
    public function cleanup(DataScope $scope, CleanupAction $action): CleanupReceipt;
}
