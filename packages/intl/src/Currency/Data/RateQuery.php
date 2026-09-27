<?php

declare(strict_types=1);

namespace CoreX\Intl\Data;

use DateTimeImmutable;

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
