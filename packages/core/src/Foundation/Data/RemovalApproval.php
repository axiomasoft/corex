<?php

declare(strict_types=1);

namespace CoreX\Foundation\Data;

use Packman\Attributes\Api;

#[Api(since: '2.0.0')]
final readonly class RemovalApproval
{
    public function __construct(public ModuleRef $provider, public PlanDigest $installed, public PlanDigest $proposed, public string $receipt) {}
}
