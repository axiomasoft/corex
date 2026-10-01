<?php

declare(strict_types=1);

namespace CoreX\Panels\Contracts;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use CoreX\Panels\Data\EntityReference;
use CoreX\Panels\Data\PanelAccess;
use CoreX\Panels\Data\PanelContext;
use CoreX\Panels\Data\PanelDescriptor;
use CoreX\Panels\Data\PanelReference;

#[Spi(kind: SpiKind::Implement, since: '1.0')]
interface PanelCatalogue
{
    /** @return list<PanelDescriptor> */
    public function forEntity(PanelContext $context, EntityReference $entity): array;

    public function access(PanelContext $context, PanelReference $panel): PanelAccess;
}
