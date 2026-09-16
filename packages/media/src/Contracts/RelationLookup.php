<?php

declare(strict_types=1);

namespace CoreX\Media\Contracts;

use CoreX\Media\Data\AttachmentInput;
use CoreX\Media\Data\AttachmentRef;
use CoreX\Media\Data\MediaContext;

interface RelationLookup
{
    public function attach(MediaContext $context, AttachmentInput $input): void;

    public function detach(MediaContext $context, AttachmentInput $input): void;

    /** @return list<AttachmentRef> */
    public function usages(MediaContext $context, string $fileId, int $limit = 50): array;
}
