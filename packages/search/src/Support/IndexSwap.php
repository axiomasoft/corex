<?php

declare(strict_types=1);

namespace CoreX\Search\Support;

final readonly class IndexSwap
{
    public function __construct(public string $fromGeneration, public string $toGeneration) {}
}
