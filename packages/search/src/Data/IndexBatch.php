<?php

declare(strict_types=1);

namespace CoreX\Search\Data;

final readonly class IndexBatch
{
    /** @param list<array<string, scalar|null>> $documents */
    public function __construct(public string $generation, public array $documents) {}
}
