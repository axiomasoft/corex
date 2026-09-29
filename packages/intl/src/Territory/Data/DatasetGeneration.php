<?php

declare(strict_types=1);

namespace CoreX\Intl\Territory\Data;

final readonly class DatasetGeneration
{
    public function __construct(
        public ?string $expectedSnapshot,
        public int $generation,
    ) {}
}
