<?php

declare(strict_types=1);

namespace CoreX\CustomFields\Schemas\Contracts;

use CoreX\CustomFields\Schemas\Data\FieldDefinition;
use CoreX\Data\Data\EntityHandle;
use Packman\Attributes\Spi;
use Packman\Attributes\SpiKind;

#[Spi(kind: SpiKind::Implement, since: '2.0.0')]
interface DeclaredFields
{
    public function owner(): string;

    /** @return list<FieldDefinition> */
    public function fields(EntityHandle $entity): array;
}
