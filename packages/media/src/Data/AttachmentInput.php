<?php

declare(strict_types=1);

namespace CoreX\Media\Data;

use CoreX\Attributes\Api;
use InvalidArgumentException;

#[Api(since: '1.0')]
final readonly class AttachmentInput
{
    public function __construct(public string $fileId, public string $entityHandle, public string $entityId, public string $slot)
    {
        if ($fileId === '' || $entityHandle === '' || $entityId === '' || $slot === '' || strlen($slot) > 64) {
            throw new InvalidArgumentException('Invalid media attachment input.');
        }
    }
}
