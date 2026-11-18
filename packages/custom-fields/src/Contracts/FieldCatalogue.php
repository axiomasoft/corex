<?php

declare(strict_types=1);

namespace CoreX\CustomFields\Contracts;

use CoreX\CustomFields\Data\FieldSnapshot;
use CoreX\Data\Data\DataScope;
use Packman\Attributes\Spi;
use Packman\Attributes\SpiKind;

#[Spi(kind: SpiKind::Implement, since: '1.0')]
interface FieldCatalogue
{
    public function snapshot(DataScope $scope, string $entity): FieldSnapshot;
}
