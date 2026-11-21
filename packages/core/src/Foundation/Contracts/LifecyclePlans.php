<?php

declare(strict_types=1);

namespace CoreX\Foundation\Contracts;

use CoreX\Data\Data\DataScope;
use CoreX\Foundation\Data\LifecyclePlan;
use CoreX\Foundation\Data\LifecyclePlanRef;
use CoreX\Foundation\Data\LifecycleStatus;
use CoreX\Foundation\Data\ModuleId;
use CoreX\Foundation\Data\PlanDigest;
use CoreX\Foundation\Enums\LifecycleAction;
use Packman\Attributes\Spi;
use Packman\Attributes\SpiKind;

#[Spi(kind: SpiKind::Call, since: '2.0.0')]
interface LifecyclePlans
{
    public function plan(DataScope $scope, ModuleId $module, LifecycleAction $action): LifecyclePlan;

    public function inspect(DataScope $scope, LifecyclePlanRef $ref): LifecycleStatus;

    public function apply(DataScope $scope, LifecyclePlanRef $ref, PlanDigest $digest): LifecycleStatus;
}
