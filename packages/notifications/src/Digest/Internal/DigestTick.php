<?php

declare(strict_types=1);

namespace CoreX\Notifications\Digest\Internal;

use CoreX\Context\Data\TenantContext;
use CoreX\Wakeup\Data\WakeupWindow;

final readonly class DigestTick
{
    public function __construct(public TenantContext $tenant, public WakeupWindow $window, public string $requestKey) {}
}
