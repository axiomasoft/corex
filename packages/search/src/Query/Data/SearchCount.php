<?php

declare(strict_types=1);

namespace CoreX\Search\Query\Data;

use CoreX\Search\Query\Enums\StatisticStatus;
use Packman\Attributes\Api;

#[Api(since: '2.0.0')]
final readonly class SearchCount
{
    public StatisticStatus $status;

    public function __construct(bool $requested, public ?int $value)
    {
        $this->status = $value !== null ? StatisticStatus::Exact : ($requested ? StatisticStatus::Unavailable : StatisticStatus::NotRequested);
    }
}
