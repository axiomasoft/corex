<?php

declare(strict_types=1);

namespace CoreX\Media\Data;

use CoreX\Attributes\Api;

#[Api(since: '1.0')]
final readonly class ConversionInput
{
    public function __construct(
        public string $sourceBytes,
        public string $sourceMimeType,
        public ConversionProfile $profile,
    ) {}
}
