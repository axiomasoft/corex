<?php

declare(strict_types=1);

namespace Flex\Crm\Deal\Events;

use Flex\Crm\Deal\Models\Deal;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

final readonly class DealCreated implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(
        public Deal $deal,
    ) {}
}
