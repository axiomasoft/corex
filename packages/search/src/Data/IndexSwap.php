<?php

declare(strict_types=1);

namespace CoreX\Search\Data;

use CoreX\Attributes\Api;

#[Api(since: '1.0')]
final readonly class IndexSwap
{
    public function __construct(public string $fromGeneration, public string $toGeneration) {}
}
