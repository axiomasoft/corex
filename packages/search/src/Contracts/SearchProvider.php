<?php

declare(strict_types=1);

namespace CoreX\Search\Contracts;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use CoreX\Data\Data\DataScope;
use CoreX\Search\Data\CandidatePage;
use CoreX\Search\Data\SearchCapabilities;
use CoreX\Search\Data\SearchQuery;

#[Spi(kind: SpiKind::Implement, since: '1.0')]
interface SearchProvider
{
    public function capabilities(): SearchCapabilities;

    public function query(DataScope $scope, SearchQuery $query): CandidatePage;
}
