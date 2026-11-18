<?php

declare(strict_types=1);

namespace CoreX\Intl\Territory\Data;

use Packman\Attributes\Api;

#[Api(since: '1.0')]
final readonly class DatasetCandidate
{
    public function __construct(
        public DatasetManifest $manifest,
        public string $immutableRef,
        public int $byteLength,
    ) {}
}
