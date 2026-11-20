<?php

declare(strict_types=1);

namespace CoreX\Search\Query\Contracts;

use CoreX\Data\Data\DataScope;
use CoreX\Search\Query\Data\SearchPage;
use CoreX\Search\Query\Data\SearchQuery;
use Packman\Attributes\Api;

#[Api(since: '2.0.0')]
interface Search
{
    public function query(DataScope $scope, SearchQuery $query): SearchPage;
}
