<?php

declare(strict_types=1);

namespace CoreX\Crm\Events;

use CoreX\Crm\Models\Deal;
use CoreX\Crm\Models\Stage;
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
