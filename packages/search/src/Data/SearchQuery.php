<?php

declare(strict_types=1);

namespace CoreX\Search\Data;

use InvalidArgumentException;
use Packman\Attributes\Api;

#[Api(since: '1.0')]
final readonly class SearchQuery
{
    /** @param list<SearchFilter> $filters */
    public function __construct(public string $entity, public string $schemaHash, public string $text, public array $filters = [], public int $limit = 25, public ?string $afterId = null, public int $scanBudget = 1000)
    {
        self::validateInput(entity: $entity, schemaHash: $schemaHash, text: $text, filters: $filters, limit: $limit, afterId: $afterId, scanBudget: $scanBudget);
    }

    /** Runtime validation also rejects values from untyped integration callers.
     * @param  array<array-key, mixed>  $filters
     */
    private static function validateInput(
        /** Runtime input. */
        string $entity,
        /** Runtime input. */
        string $schemaHash,
        /** Runtime input. */
        string $text,
        /** Runtime input. */
        array $filters = [],
        /** Runtime input. */
        int $limit = 25,
        /** Runtime input. */
        ?string $afterId = null,
        /** Runtime input. */
        int $scanBudget = 1000,
    ): void {
        if ($entity === '' || preg_match('/\A[a-f0-9]{64}\z/D', $schemaHash) !== 1 || $text === '' || strlen($text) > 4096 || count($filters) > 64 || $limit < 1 || $limit > 100 || $scanBudget < $limit || $scanBudget > 1000 || $afterId !== null && (strlen($afterId) > 2048 || preg_match('/\A[A-Za-z0-9._-]+\z/D', $afterId) !== 1)) {
            throw new InvalidArgumentException('Search query exceeds its bounded contract.');
        }

        if (count(array_filter($filters, static fn (mixed $filter): bool => ! $filter instanceof SearchFilter)) > 0) {
            throw new InvalidArgumentException('Search query filters must be typed.');
        }
    }
}
