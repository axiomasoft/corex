<?php

declare(strict_types=1);

namespace CoreX\Media\Data;

use Closure;
use Packman\Attributes\Api;

#[Api(since: '1.0')]
final readonly class MediaReadRef
{
    /** @param Closure():resource $openStream */
    public function __construct(
        public string $fileId,
        public string $mimeType,
        public int $sizeBytes,
        public string $sha256,
        public Closure $openStream,
    ) {}
}
