<?php

declare(strict_types=1);

namespace CoreX\Intl\Territory\Data;

final readonly class DatasetCandidate
{
    public function __construct(
        public DatasetManifest $manifest,
        public string $immutableRef,
        public int $byteLength,
    ) {}
}
