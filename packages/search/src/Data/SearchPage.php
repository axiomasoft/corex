<?php

declare(strict_types=1);

namespace CoreX\Search\Data;

final readonly class SearchPage
{
    /** @param list<array<string, scalar|null>> $records */
    public function __construct(public array $records, public ?string $nextAfterId, public bool $truncated) {}
}
