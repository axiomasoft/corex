<?php

declare(strict_types=1);

namespace CoreX\Crm\Events;

use CoreX\Crm\Models\Contact;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

final class ContactCreated implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(
        public readonly Contact $contact,
    ) {}
}
