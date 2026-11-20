<?php

declare(strict_types=1);

namespace CoreX\Search\Query\Data;

use CoreX\Search\Query\Enums\StatisticStatus;
use Packman\Attributes\Api;

#[Api(since: '2.0.0')]
final readonly class SearchFacets
{
    public StatisticStatus $status;

    /** @param ?array<string,array<string,int>> $values */
    public function __construct(bool $requested, public ?array $values)
    {
        $this->status = $values !== null ? StatisticStatus::Exact : ($requested ? StatisticStatus::Unavailable : StatisticStatus::NotRequested);
    }
}
