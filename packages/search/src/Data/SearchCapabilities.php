<?php

declare(strict_types=1);

namespace CoreX\Search\Data;

final readonly class SearchCapabilities
{
    public function __construct(
        public bool $fullText,
        public bool $typoTolerance = false,
        public bool $facets = false,
        public bool $highlights = false,
    ) {}
}
