<?php

declare(strict_types=1);

namespace CoreX\CustomFields\Contracts;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use CoreX\CustomFields\Data\FieldSnapshot;
use CoreX\Data\Data\DataScope;

#[Spi(kind: SpiKind::Implement, since: '1.0')]
interface FieldCatalogue
{
    public function snapshot(DataScope $scope, string $entity): FieldSnapshot;
}
