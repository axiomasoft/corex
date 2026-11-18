<?php

declare(strict_types=1);

namespace CoreX\Media\Data;

use Packman\Attributes\Api;

#[Api(since: '1.0')]
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
