<?php

declare(strict_types=1);

namespace Flex\Crm\Contact\Events;

use Flex\Crm\Contact\Models\ContactCompany;
use Flex\Crm\Context\Data\Actor;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Packman\Attributes\Internal;

#[Internal]
final readonly class ContactCompanyAssociated implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(public ContactCompany $association, public Actor $actor) {}
}
