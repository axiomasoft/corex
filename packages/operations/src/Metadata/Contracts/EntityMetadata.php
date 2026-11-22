<?php

declare(strict_types=1);

namespace CoreX\Operations\Metadata\Contracts;

use CoreX\Data\Data\DataScope;
use CoreX\Data\Data\EntityHandle;
use CoreX\Operations\Metadata\Data\MetadataView;
use Packman\Attributes\Spi;
use Packman\Attributes\SpiKind;

#[Spi(kind: SpiKind::Implement, since: '2.0.0')]
interface EntityMetadata
{
    public function describe(DataScope $scope, EntityHandle $entity): MetadataView;
}
