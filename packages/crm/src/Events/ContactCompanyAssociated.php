<?php

declare(strict_types=1);

namespace Flex\Crm\Events;

use Flex\Crm\Data\Actor;
use Flex\Crm\Models\ContactCompany;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

final class ContactCompanyAssociated implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(public readonly ContactCompany $association, public readonly Actor $actor) {}
}
