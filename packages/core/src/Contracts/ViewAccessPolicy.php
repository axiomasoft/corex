<?php

declare(strict_types=1);

namespace CoreX\Contracts;

use CoreX\SavedViews\Data\ViewAction;
use CoreX\SavedViews\Data\ViewContext;
use CoreX\SavedViews\Data\ViewSubject;

interface ViewAccessPolicy
{
    public function allows(ViewContext $context, ViewAction $action, ViewSubject $subject): bool;
}
