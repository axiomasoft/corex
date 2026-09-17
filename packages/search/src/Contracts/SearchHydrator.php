<?php

declare(strict_types=1);

namespace CoreX\Search\Contracts;

use CoreX\Search\Support\CandidatePage;
use CoreX\Search\Support\SearchPage;
use CoreX\Search\Support\SearchSnapshot;
use CoreX\Support\Data\DataScope;

interface SearchHydrator
{
    public function authorize(DataScope $scope, CandidatePage $candidates, SearchSnapshot $schema): SearchPage;
}
