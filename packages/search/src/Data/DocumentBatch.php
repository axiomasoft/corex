<?php

declare(strict_types=1);

namespace CoreX\Search\Data;

use CoreX\Attributes\Api;

#[Api(since: '1.0')]
final readonly class DocumentBatch
{
    /** @param list<array<string, scalar|null>> $documents */
    public function __construct(public array $documents, public ?DocumentCursor $next) {}
}
