<?php

declare(strict_types=1);

namespace CoreX\Media\Data;

final readonly class MediaFileRef
{
    public function __construct(
        public string $id,
        public string $kind,
        public string $mimeType,
        public int $sizeBytes,
        public string $sha256,
        public string $state,
    ) {}
}
