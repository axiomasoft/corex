<?php

declare(strict_types=1);

namespace CoreX\Data\Contracts;

use CoreX\Data\Data\DataScope;
use CoreX\Data\Data\EntityHandle;
use CoreX\Data\Data\FieldUsePlan;
use CoreX\Data\Data\FieldUseRequest;
use Packman\Attributes\Spi;
use Packman\Attributes\SpiKind;

#[Spi(kind: SpiKind::Implement, since: '2.0.0')]
interface FieldUsePolicy
{
    public function plan(DataScope $scope, EntityHandle $entity, FieldUseRequest $request): FieldUsePlan;
}
