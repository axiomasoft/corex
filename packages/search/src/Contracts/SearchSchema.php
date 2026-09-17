<?php

declare(strict_types=1);

namespace CoreX\Search\Contracts;

use CoreX\Search\Support\SearchSnapshot;
use CoreX\Support\Data\DataScope;

interface SearchSchema
{
    public function describe(DataScope $scope, string $entity): SearchSnapshot;
}
