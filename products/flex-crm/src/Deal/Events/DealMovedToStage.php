<?php

declare(strict_types=1);

namespace Flex\Crm\Deal\Events;

use Flex\Crm\Deal\Models\Deal;
use Flex\Crm\Deal\Models\Stage;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

final class DealMovedToStage implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(
        public readonly Deal $deal,
        public readonly Stage $stage,
    ) {}
}
