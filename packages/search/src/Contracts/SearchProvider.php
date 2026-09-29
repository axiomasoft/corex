<?php

declare(strict_types=1);

namespace CoreX\Search\Contracts;

use CoreX\Data\DataScope;
use CoreX\Search\Data\CandidatePage;
use CoreX\Search\Data\SearchCapabilities;
use CoreX\Search\Data\SearchQuery;

interface SearchProvider
{
    public function capabilities(): SearchCapabilities;

    public function query(DataScope $scope, SearchQuery $query): CandidatePage;
}
