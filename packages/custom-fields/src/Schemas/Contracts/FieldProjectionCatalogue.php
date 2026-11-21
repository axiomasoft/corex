<?php

declare(strict_types=1);

namespace CoreX\CustomFields\Schemas\Contracts;

use CoreX\CustomFields\Schemas\Data\FieldProjectionSchema;
use CoreX\Data\Data\DataScope;
use CoreX\Data\Data\EntityHandle;
use Packman\Attributes\Spi;
use Packman\Attributes\SpiKind;

/**
 * Provides policy-independent structural capabilities for trusted server-side composition.
 * Use FieldCatalogue for caller-visible field descriptions and FieldValues for authorized values.
 */
#[Spi(kind: SpiKind::Call, since: '2.0.0')]
interface FieldProjectionCatalogue
{
    public function snapshot(DataScope $scope, EntityHandle $entity): FieldProjectionSchema;
}
