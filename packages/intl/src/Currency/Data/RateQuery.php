<?php

declare(strict_types=1);

namespace CoreX\Intl\Currency\Data;

use DateTimeImmutable;
use Packman\Attributes\Api;

#[Api(since: '1.0')]
final readonly class RateQuery
{
    /** @param list<string> $sourceSelection */
    public function __construct(
        public string $base,
        public string $quote,
        public string $onDate,
        public array $sourceSelection,
        public ?DateTimeImmutable $publicationCutoff,
        public int $maxAgeDays,
        public bool $allowStale = false,
    ) {}
}
