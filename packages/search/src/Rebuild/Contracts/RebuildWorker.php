<?php

declare(strict_types=1);

namespace CoreX\Search\Rebuild\Contracts;

use CoreX\Data\Data\DataScope;
use CoreX\Search\Rebuild\Data\RebuildRef;
use CoreX\Search\Rebuild\Data\RebuildStatus;
use Packman\Attributes\Spi;
use Packman\Attributes\SpiKind;

#[Spi(kind: SpiKind::Call, since: '2.0.0')]
interface RebuildWorker
{
    public function barrier(DataScope $scope, RebuildRef $ref): RebuildStatus;

    public function publish(DataScope $scope, RebuildRef $ref): RebuildStatus;
}
