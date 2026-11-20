<?php

declare(strict_types=1);

namespace CoreX\CustomFields\Schemas\Contracts;

use CoreX\CustomFields\Schemas\Data\FieldId;
use CoreX\CustomFields\Schemas\Data\SchemaRevision;
use CoreX\Data\Data\DataScope;
use CoreX\Data\Data\EntityHandle;
use Packman\Attributes\Spi;
use Packman\Attributes\SpiKind;

#[Spi(kind: SpiKind::Implement, since: '2.0.0')]
interface FieldSchemaDependencies
{
    /** @return list<string> */
    public function references(DataScope $scope, EntityHandle $entity, FieldId $field, SchemaRevision $revision): array;
}
