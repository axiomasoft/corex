<?php

declare(strict_types=1);

namespace CoreX\Foundation\Data;

use CoreX\Foundation\Enums\LifecyclePhase;
use Packman\Attributes\Api;

#[Api(since: '2.0.0')]
final readonly class LifecycleStatus
{
    /**
     * @param  list<LifecycleBlocker>  $blockers
     * @param  list<CleanupReceipt>  $receipts
     */
    public function __construct(public LifecyclePlanRef $ref, public LifecyclePhase $phase, public PlanDigest $digest, public array $blockers, public array $receipts, public ?string $transitionResult = null) {}
}
