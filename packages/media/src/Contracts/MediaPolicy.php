<?php

declare(strict_types=1);

namespace CoreX\Media\Contracts;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use CoreX\Media\Data\MediaAction;
use CoreX\Media\Data\MediaContext;
use CoreX\Media\Data\MediaSubject;

#[Spi(kind: SpiKind::Implement, since: '1.0')]
interface MediaPolicy
{
    public function allows(MediaContext $context, MediaAction $action, MediaSubject $subject): bool;
}
