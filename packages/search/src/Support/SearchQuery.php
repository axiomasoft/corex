<?php

declare(strict_types=1);

namespace CoreX\Search\Support;

use InvalidArgumentException;

final readonly class SearchQuery
{
    /** @param list<mixed> $filters */
    public function __construct(
        public string $entity,
        public string $schemaHash,
        public string $text,
        public array $filters = [],
        public int $limit = 25,
        public ?string $afterId = null,
        public int $scanBudget = 1000,
    ) {
        if ($entity === '' || preg_match('/\A[a-f0-9]{64}\z/D', $schemaHash) !== 1 || $text === '' || strlen($text) > 4096 || count($filters) > 64 || $limit < 1 || $limit > 100 || $scanBudget < $limit || $scanBudget > 1000 || $afterId !== null && (strlen($afterId) > 2048 || preg_match('/\A[A-Za-z0-9._-]+\z/D', $afterId) !== 1)) {
            throw new InvalidArgumentException('Search query exceeds its bounded contract.');
        }

        if (count(array_filter($filters, static fn (mixed $filter): bool => ! $filter instanceof SearchFilter)) > 0) {
            throw new InvalidArgumentException('Search query filters must be typed.');
        }
    }
}
