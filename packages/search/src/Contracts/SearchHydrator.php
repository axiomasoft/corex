<?php

declare(strict_types=1);

namespace CoreX\Search\Contracts;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use CoreX\Data\Data\DataScope;
use CoreX\Search\Data\CandidatePage;
use CoreX\Search\Data\SearchPage;
use CoreX\Search\Data\SearchSnapshot;

#[Spi(kind: SpiKind::Implement, since: '1.0')]
interface SearchHydrator
{
    public function authorize(DataScope $scope, CandidatePage $candidates, SearchSnapshot $schema): SearchPage;
}
