<?php

declare(strict_types=1);

namespace CoreX\Intl\Currency\Data;

use CoreX\Attributes\Api;
use CoreX\Data\Data\JsonDocument;

#[Api(since: '1.0')]
final readonly class RateBatch
{
    /**
     * @param  list<RateSnapshot>  $snapshots
     */
    public function __construct(public array $snapshots, public int $expectedGeneration, public string $formatHash, public JsonDocument $sourceManifest) {}
}
