<?php

declare(strict_types=1);

namespace CoreX\Media\Data;

use Illuminate\Http\UploadedFile;
use InvalidArgumentException;

final readonly class UploadInput
{
    public function __construct(public string $requestKey, public UploadedFile $file, public string $purpose)
    {
        if ($requestKey === '' || str_contains($requestKey, "\0") || strlen($requestKey) > 128 || $purpose === '' || str_contains($purpose, "\0") || strlen($purpose) > 128) {
            throw new InvalidArgumentException('Invalid media upload input.');
        }
    }
}
