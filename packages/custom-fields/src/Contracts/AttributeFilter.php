<?php

declare(strict_types=1);

namespace CoreX\CustomFields\Contracts;

use CoreX\CustomFields\Data\FieldSnapshot;
use CoreX\CustomFields\Data\FilterNode;
use CoreX\CustomFields\Data\FilterPlan;
use CoreX\Data\DataScope;

interface AttributeFilter
{
    public function compile(DataScope $scope, FieldSnapshot $schema, FilterNode $filter): FilterPlan;
}
