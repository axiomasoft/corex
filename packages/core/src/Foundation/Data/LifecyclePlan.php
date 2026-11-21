<?php

declare(strict_types=1);

namespace CoreX\Foundation\Data;

use CoreX\Foundation\Enums\LifecycleAction;
use Packman\Attributes\Api;

#[Api(since: '2.0.0')]
final readonly class LifecyclePlan
{
    public function __construct(public LifecyclePlanRef $ref, public ModuleId $module, public LifecycleAction $action, public PlanDigest $digest) {}
}
