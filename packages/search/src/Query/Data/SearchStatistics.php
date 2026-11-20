<?php

declare(strict_types=1);

namespace CoreX\Search\Query\Data;

use InvalidArgumentException;
use Packman\Attributes\Api;

#[Api(since: '2.0.0')]
final readonly class SearchStatistics
{
    /** @param ?array<string,array<string,int>> $facets */
    public function __construct(public ?int $count, public ?array $facets)
    {
        if ($count !== null && $count < 0) {
            throw new InvalidArgumentException('Negative search count.');
        }
        foreach ($facets ?? [] as $buckets) {
            if (array_any($buckets, static fn (int $value): bool => $value < 0)) {
                throw new InvalidArgumentException('Negative facet count.');
            }
        }
    }
}
