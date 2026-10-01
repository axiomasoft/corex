<?php

declare(strict_types=1);

namespace CoreX\Search\Data;

use CoreX\Attributes\Api;

#[Api(since: '1.0')]
final readonly class SearchPage
{
    /** @param list<array<string, scalar|null>> $records */
    public function __construct(public array $records, public ?string $nextAfterId, public bool $truncated) {}
}
