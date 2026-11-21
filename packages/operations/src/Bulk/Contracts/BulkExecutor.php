<?php

declare(strict_types=1);

namespace CoreX\Operations\Bulk\Contracts;

use CoreX\Operations\Bulk\Data\BulkContext;
use CoreX\Operations\Bulk\Data\BulkStep;
use CoreX\Operations\Bulk\Data\BulkStepResult;
use Packman\Attributes\Spi;
use Packman\Attributes\SpiKind;

#[Spi(kind: SpiKind::Implement, since: '2.0.0')]
interface BulkExecutor
{
    public function step(BulkContext $context, BulkStep $step): BulkStepResult;
}
