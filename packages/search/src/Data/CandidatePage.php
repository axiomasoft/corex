<?php

declare(strict_types=1);

namespace CoreX\Search\Data;

use Packman\Attributes\Api;

#[Api(since: '1.0')]
final readonly class CandidatePage
{
    /** @param list<Candidate> $candidates */
    public function __construct(public array $candidates, public ?string $nextAfterId, public bool $truncated) {}
}
