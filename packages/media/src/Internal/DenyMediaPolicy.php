<?php

declare(strict_types=1);

namespace CoreX\Media\Internal;

use CoreX\Media\Contracts\MediaPolicy;
use CoreX\Media\Data\MediaAction;
use CoreX\Media\Data\MediaContext;
use CoreX\Media\Data\MediaSubject;

final class DenyMediaPolicy implements MediaPolicy
{
    public function allows(MediaContext $context, MediaAction $action, MediaSubject $subject): bool
    {
        return false;
    }
}
