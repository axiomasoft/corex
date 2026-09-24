<?php

declare(strict_types=1);

namespace CoreX\Attributes\Internal\Schema;

final readonly class AttributeIndexReceipt
{
    /**
     * @param  list<string>  $dirtyRecords
     * @param  array<array-key, mixed>|null  $explainBefore
     * @param  array<array-key, mixed>|null  $explainAfter
     */
    public function __construct(
        public string $mode,
        public bool $ddl,
        public ?string $indexName,
        public ?string $expression,
        public ?string $orderBy,
        public string $owner,
        public array $dirtyRecords = [],
        public ?array $explainBefore = null,
        public ?array $explainAfter = null,
    ) {}
}
