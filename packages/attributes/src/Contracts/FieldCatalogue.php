<?php

declare(strict_types=1);

namespace CoreX\Attributes\Contracts;

use CoreX\Attributes\Support\FieldSnapshot;
use CoreX\Support\Data\DataScope;

interface FieldCatalogue
{
    public function snapshot(DataScope $scope, string $entity): FieldSnapshot;
}
