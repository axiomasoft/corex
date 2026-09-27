<?php

declare(strict_types=1);

namespace CoreX\Notifications\Internal\Digest;

use CoreX\Tenancy\TenantContext;
use CoreX\Wakeup\Data\WakeupWindow;

final readonly class DigestTick
{
    public function __construct(public TenantContext $tenant, public WakeupWindow $window, public string $requestKey) {}
}
