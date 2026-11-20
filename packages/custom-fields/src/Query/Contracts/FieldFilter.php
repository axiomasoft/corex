<?php

declare(strict_types=1);

namespace CoreX\CustomFields\Query\Contracts;

use CoreX\CustomFields\Query\Data\FilterNode;
use CoreX\CustomFields\Query\Data\FilterPlan;
use CoreX\CustomFields\Schemas\Data\FieldSchemaSnapshot;
use CoreX\Data\Data\DataScope;
use Packman\Attributes\Spi;
use Packman\Attributes\SpiKind;

#[Spi(kind: SpiKind::Call, since: '2.0.0')]
interface FieldFilter
{
    public function compile(DataScope $scope, FieldSchemaSnapshot $schema, FilterNode $filter): FilterPlan;
}
