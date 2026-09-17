<?php

declare(strict_types=1);

namespace CoreX\Media\Data;

use InvalidArgumentException;

final readonly class ConversionProfile
{
    public function __construct(
        public string $key,
        public string $version,
        public int $maxWidth,
        public int $maxHeight,
        public int $maxPixels,
        public int $maxOutputBytes,
    ) {
        if ($key === '' || $version === '' || $maxWidth < 1 || $maxHeight < 1 || $maxPixels < 1 || $maxOutputBytes < 1) {
            throw new InvalidArgumentException('Conversion profile is invalid.');
        }
    }

    public function digest(string $implementation): string
    {
        return hash('sha256', json_encode([
            'implementation' => $implementation,
            'key' => $this->key,
            'version' => $this->version,
            'max_width' => $this->maxWidth,
            'max_height' => $this->maxHeight,
            'max_pixels' => $this->maxPixels,
            'max_output_bytes' => $this->maxOutputBytes,
        ], JSON_THROW_ON_ERROR));
    }
}
