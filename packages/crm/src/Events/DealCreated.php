<?php

declare(strict_types=1);

namespace CoreX\Crm\Events;

use CoreX\Crm\Models\Deal;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

final class DealCreated implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(
        public readonly Deal $deal,
    ) {}
}
