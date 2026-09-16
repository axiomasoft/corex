<?php

declare(strict_types=1);

namespace CoreX\Media\Contracts;

use CoreX\Media\Data\MediaAction;
use CoreX\Media\Data\MediaContext;
use CoreX\Media\Data\MediaSubject;

interface MediaPolicy
{
    public function allows(MediaContext $context, MediaAction $action, MediaSubject $subject): bool;
}
