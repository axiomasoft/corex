<?php

declare(strict_types=1);

namespace Flex\Crm\Events;

use Flex\Crm\Models\Activity;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

final class ActivityLogged implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(
        public readonly Activity $activity,
    ) {}
}
