<?php

declare(strict_types=1);

namespace CoreX\Search\Query\Contracts;

use Closure;
use CoreX\Data\Data\DataScope;
use CoreX\Search\Query\Data\CandidateRequest;
use CoreX\Search\Query\Data\SearchPage;
use CoreX\Search\Query\Data\SearchStatistics;
use Packman\Attributes\Spi;
use Packman\Attributes\SpiKind;

#[Spi(kind: SpiKind::Implement, since: '2.0.0')]
interface ExactCandidateDriver extends LiveCandidateDriver
{
    /** @param Closure(bool):SearchPage $query Receives true only inside a verified shared owner/ACL snapshot. */
    public function inSnapshot(DataScope $scope, Closure $query): SearchPage;

    /** @param list<string> $facets */
    public function statistics(CandidateRequest $request, bool $count, array $facets): SearchStatistics;
}
