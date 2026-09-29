<?php

declare(strict_types=1);

namespace CoreX\Search\Contracts;

use CoreX\Data\DataScope;
use CoreX\Search\Support\CandidatePage;
use CoreX\Search\Support\SearchPage;
use CoreX\Search\Support\SearchSnapshot;

interface SearchHydrator
{
    public function authorize(DataScope $scope, CandidatePage $candidates, SearchSnapshot $schema): SearchPage;
}
