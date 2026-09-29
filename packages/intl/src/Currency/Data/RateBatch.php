<?php

declare(strict_types=1);

namespace CoreX\Intl\Currency\Data;

final readonly class RateBatch
{
    /** @param list<RateSnapshot> $snapshots | @param array<string, null|bool|int|string|array> $sourceManifest */
    public function __construct(
        public array $snapshots,
        public int $expectedGeneration,
        public string $formatHash,
        public array $sourceManifest,
    ) {}
}
