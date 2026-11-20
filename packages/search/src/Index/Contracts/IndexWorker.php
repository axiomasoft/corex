<?php

declare(strict_types=1);

namespace CoreX\Search\Index\Contracts;

use CoreX\Data\Data\DataScope;
use CoreX\Data\Data\EntityHandle;
use Packman\Attributes\Spi;
use Packman\Attributes\SpiKind;

#[Spi(kind: SpiKind::Call, since: '2.0.0')]
interface IndexWorker
{
    public function advance(DataScope $scope, EntityHandle $entity, int $generation): bool;
}
