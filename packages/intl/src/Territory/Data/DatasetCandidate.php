<?php

declare(strict_types=1);

namespace CoreX\Intl\Territory\Data;

use CoreX\Attributes\Api;

#[Api(since: '1.0')]
final readonly class DatasetCandidate
{
    public function __construct(
        public DatasetManifest $manifest,
        public string $immutableRef,
        public int $byteLength,
    ) {}
}
