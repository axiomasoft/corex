<?php

declare(strict_types=1);

namespace Flex\Crm\Lead\Events;

use Flex\Crm\Lead\Models\Lead;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

final readonly class LeadCreated implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(
        public Lead $lead,
    ) {}
}
