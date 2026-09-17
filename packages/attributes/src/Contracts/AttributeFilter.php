<?php

declare(strict_types=1);

namespace CoreX\Attributes\Contracts;

use CoreX\Attributes\Support\FieldSnapshot;
use CoreX\Attributes\Support\FilterNode;
use CoreX\Attributes\Support\FilterPlan;
use CoreX\Support\Data\DataScope;

interface AttributeFilter
{
    public function compile(DataScope $scope, FieldSnapshot $schema, FilterNode $filter): FilterPlan;
}
