<?php

declare(strict_types=1);

namespace CoreX\Search\Contracts;

use CoreX\Data\DataScope;
use CoreX\Search\Data\CandidatePage;
use CoreX\Search\Data\SearchPage;
use CoreX\Search\Data\SearchSnapshot;

interface SearchHydrator
{
    public function authorize(DataScope $scope, CandidatePage $candidates, SearchSnapshot $schema): SearchPage;
}
