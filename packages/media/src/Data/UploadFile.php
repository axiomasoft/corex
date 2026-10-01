<?php

declare(strict_types=1);

namespace CoreX\Media\Data;

use CoreX\Attributes\Api;

/** Immutable upload bytes and metadata; admission remains in MediaLibrary::store. */
#[Api(since: '1.0')]
final readonly class UploadFile
{
    public function __construct(
        public ?string $bytes,
        public string $originalName,
        public bool $valid,
        public int $size,
        public ?string $mimeType,
    ) {}
}
