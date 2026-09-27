<?php

declare(strict_types=1);

namespace Flex\Crm\Events;

use Flex\Crm\Data\Actor;
use Flex\Crm\Models\Contact;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

final class ContactCreated implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(
        public readonly Contact $contact,
        public readonly Actor $actor,
    ) {}
}
