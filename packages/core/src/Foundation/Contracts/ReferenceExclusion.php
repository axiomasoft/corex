<?php

declare(strict_types=1);

namespace CoreX\Foundation\Contracts;

use CoreX\Data\Data\DataScope;
use CoreX\Foundation\Data\LifecyclePlanRef;
use CoreX\Foundation\Data\ModuleRef;
use CoreX\Foundation\Data\OwnerLease;
use Packman\Attributes\Spi;
use Packman\Attributes\SpiKind;

#[Spi(kind: SpiKind::Implement, since: '2.0.0')]
interface ReferenceExclusion
{
    public function hold(DataScope $scope, ModuleRef $module, LifecyclePlanRef $plan, ?OwnerLease $previous): OwnerLease;

    /** Assert the durable writer exclusion still covers the full transition commit. */
    public function verify(DataScope $scope, OwnerLease $lease): void;

    public function release(DataScope $scope, OwnerLease $lease): void;
}
