<?php

declare(strict_types=1);

namespace CoreX\Media\Data;

use InvalidArgumentException;
use Packman\Attributes\Api;

#[Api(since: '1.0')]
final readonly class UploadInput
{
    public function __construct(public string $requestKey, public UploadFile $file, public string $purpose)
    {
        if ($requestKey === '' || str_contains($requestKey, "\0") || strlen($requestKey) > 128 || $purpose === '' || str_contains($purpose, "\0") || strlen($purpose) > 128) {
            throw new InvalidArgumentException('Invalid media upload input.');
        }
    }
}
