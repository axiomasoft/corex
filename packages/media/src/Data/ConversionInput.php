<?php

declare(strict_types=1);

namespace CoreX\Media\Data;

final readonly class ConversionInput
{
    public function __construct(
        public string $sourceBytes,
        public string $sourceMimeType,
        public ConversionProfile $profile,
    ) {}
}
