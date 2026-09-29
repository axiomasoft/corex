<?php

declare(strict_types=1);

namespace Flex\Crm\Contact\Events;

use Flex\Crm\Contact\Models\ContactCompany;
use Flex\Crm\Context\Data\Actor;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

final class ContactCompanyAssociated implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(public readonly ContactCompany $association, public readonly Actor $actor) {}
}
