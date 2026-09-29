<?php

declare(strict_types=1);

namespace CoreX\Panels\SavedViews\Internal;

use CoreX\Panels\SavedViews\Contracts\ViewAccessPolicy;
use CoreX\Panels\SavedViews\Data\ViewAction;
use CoreX\Panels\SavedViews\Data\ViewContext;
use CoreX\Panels\SavedViews\Data\ViewSubject;

/** @internal */
final class DenyViewAccessPolicy implements ViewAccessPolicy
{
    public function allows(ViewContext $context, ViewAction $action, ViewSubject $subject): bool
    {
        return false;
    }
}
