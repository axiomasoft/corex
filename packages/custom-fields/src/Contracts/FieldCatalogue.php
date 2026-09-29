<?php

declare(strict_types=1);

namespace CoreX\CustomFields\Contracts;

use CoreX\CustomFields\Support\FieldSnapshot;
use CoreX\Data\DataScope;

interface FieldCatalogue
{
    public function snapshot(DataScope $scope, string $entity): FieldSnapshot;
}
