<?php

declare(strict_types=1);

namespace CoreX\Search\Internal;

use CoreX\Data\DataScope;
use CoreX\Search\Contracts\SearchProvider;
use CoreX\Search\Support\CandidatePage;
use CoreX\Search\Support\SearchCapabilities;
use CoreX\Search\Support\SearchQuery;

final class UnavailableSearchProvider implements SearchProvider
{
    public function capabilities(): SearchCapabilities
    {
        return new SearchCapabilities(fullText: false);
    }

    public function query(DataScope $scope, SearchQuery $query): CandidatePage
    {
        return new CandidatePage(candidates: [], nextAfterId: null, truncated: false);
    }
}
