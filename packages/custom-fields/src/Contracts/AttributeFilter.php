<?php

declare(strict_types=1);

namespace CoreX\CustomFields\Contracts;

use CoreX\CustomFields\Support\FieldSnapshot;
use CoreX\CustomFields\Support\FilterNode;
use CoreX\CustomFields\Support\FilterPlan;
use CoreX\Data\DataScope;

interface AttributeFilter
{
    public function compile(DataScope $scope, FieldSnapshot $schema, FilterNode $filter): FilterPlan;
}
