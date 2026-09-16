<?php

declare(strict_types=1);

namespace CoreX\Media\Data;

final readonly class AttachmentRef
{
    public function __construct(public string $fileId, public string $entityHandle, public string $entityId, public string $slot) {}
}
