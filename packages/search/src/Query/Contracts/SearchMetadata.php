<?php

declare(strict_types=1);

namespace CoreX\Search\Query\Contracts;

use CoreX\Data\Data\DataScope;
use CoreX\Data\Data\EntityHandle;
use CoreX\Search\Query\Data\SearchDescription;
use Packman\Attributes\Spi;
use Packman\Attributes\SpiKind;

#[Spi(kind: SpiKind::Call, since: '2.0.0')]
interface SearchMetadata
{
    public function describe(DataScope $scope, EntityHandle $entity): SearchDescription;
}
