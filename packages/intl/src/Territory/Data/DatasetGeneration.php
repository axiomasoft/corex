<?php

declare(strict_types=1);

namespace CoreX\Intl\Territory\Data;

use Packman\Attributes\Api;

#[Api(since: '1.0')]
final readonly class DatasetGeneration
{
    public function __construct(
        public ?string $expectedSnapshot,
        public int $generation,
    ) {}
}
