<?php

declare(strict_types=1);

namespace CoreX\Search\Query\Data;

use Packman\Attributes\Api;

#[Api(since: '2.0.0')]
final readonly class SearchPage
{
    /** @param list<SearchHit> $hits */
    public function __construct(public array $hits, public ?string $cursor, public SearchCount $count, public SearchFacets $facets) {}
}
