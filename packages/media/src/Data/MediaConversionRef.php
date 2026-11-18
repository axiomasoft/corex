<?php

declare(strict_types=1);

namespace CoreX\Media\Data;

use Packman\Attributes\Api;

#[Api(since: '1.0')]
final readonly class MediaConversionRef
{
    public function __construct(
        public int $id,
        public string $fileId,
        public string $profile,
        public string $mimeType,
        public int $sizeBytes,
        public int $width,
        public int $height,
    ) {}
}
