<?php

declare(strict_types=1);

namespace CoreX\Media\Contracts;

use CoreX\Attributes\Api;
use CoreX\Media\Data\DeleteResult;
use CoreX\Media\Data\MediaContext;
use CoreX\Media\Data\MediaConversionRef;
use CoreX\Media\Data\MediaFileRef;
use CoreX\Media\Data\MediaReadRef;
use CoreX\Media\Data\UploadInput;

#[Api(since: '1.0')]
interface MediaLibrary
{
    public function store(MediaContext $context, UploadInput $input): MediaFileRef;

    public function read(MediaContext $context, string $fileId): MediaReadRef;

    /** Ready output only; null until the requested current profile has been published. */
    public function readConversion(MediaContext $context, string $fileId, string $profile): ?MediaReadRef;

    public function delete(MediaContext $context, string $fileId): DeleteResult;

    public function conversion(MediaContext $context, string $fileId, string $profile): ?MediaConversionRef;
}
