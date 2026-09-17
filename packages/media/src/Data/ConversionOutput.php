<?php

declare(strict_types=1);

namespace CoreX\Media\Data;

use InvalidArgumentException;

final readonly class ConversionOutput
{
    public function __construct(
        public string $bytes,
        public string $mimeType,
        public int $width,
        public int $height,
    ) {
        if ($bytes === '' || $mimeType === '' || $width < 1 || $height < 1) {
            throw new InvalidArgumentException('Conversion output is invalid.');
        }
    }
}
