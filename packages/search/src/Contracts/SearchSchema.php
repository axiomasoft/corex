<?php

declare(strict_types=1);

namespace CoreX\Search\Contracts;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use CoreX\Data\Data\DataScope;
use CoreX\Search\Data\SearchSnapshot;

#[Spi(kind: SpiKind::Implement, since: '1.0')]
interface SearchSchema
{
    public function describe(DataScope $scope, string $entity): SearchSnapshot;
}
