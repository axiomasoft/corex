<?php

declare(strict_types=1);

namespace CoreX\Media\Data;

final readonly class MediaSubject
{
    /** @param array<string, bool|int|string> $metadata */
    public function __construct(
        public ?MediaFileRef $file = null,
        public ?AttachmentInput $attachment = null,
        public array $metadata = [],
    ) {}
}
