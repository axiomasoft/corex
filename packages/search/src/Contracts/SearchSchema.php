<?php

declare(strict_types=1);

namespace CoreX\Search\Contracts;

use CoreX\Data\DataScope;
use CoreX\Search\Support\SearchSnapshot;

interface SearchSchema
{
    public function describe(DataScope $scope, string $entity): SearchSnapshot;
}
