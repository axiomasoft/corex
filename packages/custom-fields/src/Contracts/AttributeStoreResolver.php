<?php

declare(strict_types=1);

namespace CoreX\CustomFields\Contracts;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;

#[Spi(kind: SpiKind::Implement, since: '1.0')]
interface AttributeStoreResolver
{
    public function forEntity(string $entity): AttributeStore;
}
