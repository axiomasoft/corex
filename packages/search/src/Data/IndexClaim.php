<?php

declare(strict_types=1);

namespace CoreX\Search\Data;

final readonly class IndexClaim
{
    /** @param list<array<string, scalar|null>> $records */
    public function __construct(
        public string $laneKey,
        public string $generation,
        public string $restoreEpoch,
        public int $claimEpoch,
        public array $records,
    ) {}
}
