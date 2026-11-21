<?php

declare(strict_types=1);

namespace CoreX\Foundation\Data;

use Packman\Attributes\Api;

#[Api(since: '2.0.0')]
final readonly class LifecycleConfirmation
{
    public function __construct(public LifecyclePlanRef $ref, public PlanDigest $digest) {}
}
