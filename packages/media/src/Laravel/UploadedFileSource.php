<?php

declare(strict_types=1);

namespace CoreX\Media\Laravel;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use CoreX\Media\Data\UploadFile;
use CoreX\Media\Internal\Support\MediaOptions;
use Illuminate\Http\UploadedFile;

/** Captures the framework upload before it enters the scalar media command contract. */
#[Spi(kind: SpiKind::Call, since: '1.0')]
final class UploadedFileSource
{
    public static function fromFile(UploadedFile $file): UploadFile
    {
        $path = $file->getRealPath();
        $size = (int) $file->getSize();
        $limit = resolve(MediaOptions::class)->maxUploadBytes;
        $bytes = $path !== false && $file->isValid() && $size > 0 && $size <= $limit
            ? file_get_contents($path, length: $limit + 1)
            : false;

        return new UploadFile(
            bytes: $bytes !== false && strlen($bytes) <= $limit ? $bytes : null,
            originalName: $file->getClientOriginalName(),
            valid: $file->isValid(),
            size: $size,
            mimeType: $file->getMimeType(),
        );
    }
}
