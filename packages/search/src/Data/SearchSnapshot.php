<?php

declare(strict_types=1);

namespace CoreX\Search\Data;

use CoreX\Attributes\Api;
use InvalidArgumentException;

#[Api(since: '1.0')]
final readonly class SearchSnapshot
{
    /**
     * @param  list<string>  $searchableColumns
     * @param  array<string, string>  $filterColumns
     * @param  list<string>  $resultColumns
     */
    public function __construct(
        public string $entity,
        public string $schemaHash,
        public string $table,
        public string $idColumn,
        public array $searchableColumns,
        public array $filterColumns = [],
        public array $resultColumns = [],
    ) {
        foreach ([$table, $idColumn, ...$searchableColumns, ...array_values($filterColumns), ...$resultColumns] as $identifier) {
            if (preg_match('/\A[a-z_][a-z0-9_]*\z/D', $identifier) !== 1) {
                throw new InvalidArgumentException('Search schema contains an unsafe SQL identifier.');
            }
        }

        if ($searchableColumns === [] || preg_match('/\A[a-f0-9]{64}\z/D', $schemaHash) !== 1) {
            throw new InvalidArgumentException('Search schema is incomplete.');
        }
    }
}
