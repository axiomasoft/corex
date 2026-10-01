<?php

declare(strict_types=1);

namespace CoreX\Media\Contracts;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use CoreX\Media\Data\AttachmentInput;
use CoreX\Media\Data\AttachmentRef;
use CoreX\Media\Data\MediaContext;

#[Spi(kind: SpiKind::Implement, since: '1.0')]
interface RelationLookup
{
    public function attach(MediaContext $context, AttachmentInput $input): void;

    public function detach(MediaContext $context, AttachmentInput $input): void;

    /** @return list<AttachmentRef> */
    public function usages(MediaContext $context, string $fileId, int $limit = 50): array;
}
