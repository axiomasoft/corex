<?php

declare(strict_types=1);

namespace CoreX\Panels\SavedViews\Contracts;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use CoreX\Panels\SavedViews\Data\ViewAction;
use CoreX\Panels\SavedViews\Data\ViewContext;
use CoreX\Panels\SavedViews\Data\ViewSubject;

#[Spi(kind: SpiKind::Implement, since: '1.0')]
interface ViewAccessPolicy
{
    public function allows(ViewContext $context, ViewAction $action, ViewSubject $subject): bool;
}
