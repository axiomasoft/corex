<?php

declare(strict_types=1);

namespace CoreX\Panels\Contracts;

use CoreX\Panels\Data\PanelAccess;
use CoreX\Panels\Data\PanelContext;
use CoreX\Panels\Data\PanelDescriptor;
use CoreX\Panels\Data\PanelReference;

interface PanelProvider
{
    public function descriptor(): PanelDescriptor;

    public function access(PanelContext $context, PanelReference $panel): PanelAccess;
}
