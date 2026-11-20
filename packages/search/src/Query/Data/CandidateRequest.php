<?php

declare(strict_types=1);

namespace CoreX\Search\Query\Data;

use CoreX\Data\Data\DataScope;
use CoreX\Search\Index\Data\IndexTarget;
use Packman\Attributes\Api;

#[Api(since: '2.0.0')]
final readonly class CandidateRequest
{
    /**
     * @param  list<string>  $searchFields
     * @param  list<SearchFilter>  $filters
     * @param  list<SearchSort>  $sort
     * @param  ?list<string>  $recordIds  Null means an unconditional grant, empty means an empty corpus.
     */
    public function __construct(
        public DataScope $scope,
        public IndexTarget $generation,
        public ?string $text,
        public array $searchFields,
        public array $filters,
        public array $sort,
        public ?array $recordIds,
        public ?string $position,
        public int $limit,
    ) {}
}
