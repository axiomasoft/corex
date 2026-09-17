<?php

declare(strict_types=1);

namespace CoreX\Search\Support;

final readonly class CandidatePage
{
    /** @param list<Candidate> $candidates */
    public function __construct(public array $candidates, public ?string $nextAfterId, public bool $truncated) {}
}
