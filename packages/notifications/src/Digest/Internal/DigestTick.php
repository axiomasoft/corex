<?php

declare(strict_types=1);

namespace CoreX\Notifications\Digest\Internal;

use CoreX\Context\Data\TenantContext;
use CoreX\Data\Data\DataScope;
use CoreX\Scheduling\Data\WakeupWindow;
use Packman\Attributes\Internal;

#[Internal]
final readonly class DigestTick
{
    public function __construct(public TenantContext $tenant, public DataScope $scope, public WakeupWindow $window, public string $requestKey) {}
}
