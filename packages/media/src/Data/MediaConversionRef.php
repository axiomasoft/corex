<?php

declare(strict_types=1);

namespace CoreX\Media\Data;

use Packman\Attributes\Api;

#[Api(since: '1.0')]
final readonly class MediaConversionRef
{
    public function __construct(public string $id, public string $fileId, public string $profile) {}
}
