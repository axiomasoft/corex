<?php

declare(strict_types=1);

namespace CoreX\Search\Contracts;

use CoreX\Data\DataScope;
use CoreX\Search\Support\CandidatePage;
use CoreX\Search\Support\SearchCapabilities;
use CoreX\Search\Support\SearchQuery;

interface SearchProvider
{
    public function capabilities(): SearchCapabilities;

    public function query(DataScope $scope, SearchQuery $query): CandidatePage;
}
