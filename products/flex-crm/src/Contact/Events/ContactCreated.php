<?php

declare(strict_types=1);

namespace Flex\Crm\Contact\Events;

use Flex\Crm\Contact\Models\Contact;
use Flex\Crm\Context\Data\Actor;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

final readonly class ContactCreated implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(
        public Contact $contact,
        public Actor $actor,
    ) {}
}
