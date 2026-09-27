<?php

declare(strict_types=1);

namespace CoreX\SavedViews\Internal;

use CoreX\Contracts\ViewAccessPolicy;
use CoreX\SavedViews\Data\ViewAction;
use CoreX\SavedViews\Data\ViewContext;
use CoreX\SavedViews\Data\ViewSubject;

/** @internal */
final class DenyViewAccessPolicy implements ViewAccessPolicy
{
    public function allows(ViewContext $context, ViewAction $action, ViewSubject $subject): bool
    {
        return false;
    }
}
