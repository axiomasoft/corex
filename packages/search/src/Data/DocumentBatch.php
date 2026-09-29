<?php

declare(strict_types=1);

namespace CoreX\Search\Data;

final readonly class DocumentBatch
{
    /** @param list<array<string, scalar|null>> $documents */
    public function __construct(public array $documents, public ?DocumentCursor $next) {}
}
