<?php

declare(strict_types=1);

namespace CoreX\Search\Query\Data;

use Packman\Attributes\Api;

#[Api(since: '2.0.0')]
final readonly class DriverCapabilities
{
    public function __construct(
        public bool $text = false,
        public bool $filter = false,
        public bool $sort = false,
        public bool $scopedCorpus = false,
    ) {}
}
