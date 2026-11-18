<?php

declare(strict_types=1);

namespace CoreX\Search\Contracts;

use CoreX\Data\Data\DataScope;
use CoreX\Search\Data\SearchSnapshot;
use Packman\Attributes\Spi;
use Packman\Attributes\SpiKind;

#[Spi(kind: SpiKind::Implement, since: '1.0')]
interface SearchSchema
{
    public function describe(DataScope $scope, string $entity): SearchSnapshot;
}
