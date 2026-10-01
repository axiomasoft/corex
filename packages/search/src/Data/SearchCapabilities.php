<?php

declare(strict_types=1);

namespace CoreX\Search\Data;

use CoreX\Attributes\Api;

#[Api(since: '1.0')]
final readonly class SearchCapabilities
{
    public function __construct(
        public bool $fullText,
        public bool $typoTolerance = false,
        public bool $facets = false,
        public bool $highlights = false,
    ) {}
}
