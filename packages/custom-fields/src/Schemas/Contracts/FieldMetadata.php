<?php

declare(strict_types=1);

namespace CoreX\CustomFields\Schemas\Contracts;

use CoreX\CustomFields\Schemas\Data\FieldDescription;
use CoreX\Data\Data\DataScope;
use CoreX\Data\Data\EntityHandle;
use Packman\Attributes\Spi;
use Packman\Attributes\SpiKind;

#[Spi(kind: SpiKind::Call, since: '2.0.0')]
interface FieldMetadata
{
    public function describe(DataScope $scope, EntityHandle $entity): FieldDescription;
}
