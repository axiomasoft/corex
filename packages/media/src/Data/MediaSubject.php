<?php

declare(strict_types=1);

namespace CoreX\Media\Data;

use Packman\Attributes\Api;

#[Api(since: '1.0')]
final readonly class MediaSubject
{
    /** @param array<string, bool|int|string> $metadata */
    public function __construct(
        public ?MediaFileRef $file = null,
        public ?AttachmentInput $attachment = null,
        public array $metadata = [],
    ) {}
}
