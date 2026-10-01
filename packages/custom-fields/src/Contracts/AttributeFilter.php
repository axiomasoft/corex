<?php

declare(strict_types=1);

namespace CoreX\CustomFields\Contracts;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use CoreX\CustomFields\Data\FieldSnapshot;
use CoreX\CustomFields\Data\FilterNode;
use CoreX\CustomFields\Data\FilterPlan;
use CoreX\Data\Data\DataScope;

#[Spi(kind: SpiKind::Implement, since: '1.0')]
interface AttributeFilter
{
    public function compile(DataScope $scope, FieldSnapshot $schema, FilterNode $filter): FilterPlan;
}
