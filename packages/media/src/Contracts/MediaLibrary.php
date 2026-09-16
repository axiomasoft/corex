<?php

declare(strict_types=1);

namespace CoreX\Media\Contracts;

use CoreX\Media\Data\DeleteResult;
use CoreX\Media\Data\MediaContext;
use CoreX\Media\Data\MediaConversionRef;
use CoreX\Media\Data\MediaFileRef;
use CoreX\Media\Data\MediaReadRef;
use CoreX\Media\Data\UploadInput;

interface MediaLibrary
{
    public function store(MediaContext $context, UploadInput $input): MediaFileRef;

    public function read(MediaContext $context, string $fileId): MediaReadRef;

    public function delete(MediaContext $context, string $fileId): DeleteResult;

    public function conversion(MediaContext $context, string $fileId, string $profile): ?MediaConversionRef;
}
