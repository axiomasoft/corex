<?php

declare(strict_types=1);

namespace CoreX\Search\Query\Data;

use CoreX\Data\Data\EntityHandle;
use CoreX\Data\Data\FieldSelection;
use CoreX\Search\Projection\Data\ProjectionInput;
use InvalidArgumentException;
use Packman\Attributes\Api;

#[Api(since: '2.0.0')]
final readonly class SearchQuery
{
    /**
     * @param  list<string>  $searchFields
     * @param  list<SearchFilter>  $filters
     * @param  list<SearchSort>  $sort
     * @param  list<string>  $fields
     * @param  list<string>  $facets
     */
    public function __construct(
        public EntityHandle $entity,
        public ?string $text = null,
        public array $searchFields = [],
        public array $filters = [],
        public array $sort = [],
        public array $fields = [],
        public int $limit = 25,
        public ?string $cursor = null,
        public bool $count = false,
        public array $facets = [],
    ) {
        foreach ([$searchFields, $fields, $facets] as $selection) {
            new FieldSelection($selection);
        }
        ProjectionInput::objects($filters, SearchFilter::class, 64);
        ProjectionInput::objects($sort, SearchSort::class, 16);

        if (($text === null) !== ($searchFields === []) || $text === '' || strlen($text ?? '') > 4096
            || $limit < 1 || $limit > 100 || count(array_unique(array_column($sort, 'field'))) !== count($sort)
            || ($cursor !== null && ($cursor === '' || strlen($cursor) > 16384))) {
            throw new InvalidArgumentException('Invalid search query.');
        }
    }
}
