<?php

declare(strict_types=1);

namespace CoreX\Crm\Events;

use CoreX\Crm\Models\Contact;
use CoreX\Crm\Models\Lead;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

final class LeadConverted implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(
        public readonly Lead $lead,
        public readonly Contact $contact,
    ) {}
}
