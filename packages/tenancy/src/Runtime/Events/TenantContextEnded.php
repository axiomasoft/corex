<?php

declare(strict_types=1);

namespace CoreX\Tenancy\Runtime\Events;

use Packman\Attributes\Api;

/** Manual end/setWorkspace notification after Tenant restored the adapter-owned frame. Protected runFor uses Tenant observations. */
#[Api(since: '1.0')]
final readonly class TenantContextEnded
{
    public function __construct(public string $accountId) {}
}
