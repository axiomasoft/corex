<?php

declare(strict_types=1);

namespace Flex\Crm\Activity\Events;

use Flex\Crm\Activity\Models\Activity;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Packman\Attributes\Internal;

#[Internal]
final readonly class ActivityLogged implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(
        public Activity $activity,
    ) {}
}
