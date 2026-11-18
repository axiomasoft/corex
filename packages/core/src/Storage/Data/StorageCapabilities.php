<?php

declare(strict_types=1);

namespace CoreX\Storage\Data;

use Packman\Attributes\Api;

#[Api(since: '1.0')]
final readonly class StorageCapabilities
{
    public function __construct(
        public bool $usage,
        public bool $export,
    ) {}
}
