<?php

declare(strict_types=1);

namespace Flex\Crm\Company\Events;

use Flex\Crm\Company\Models\Company;
use Flex\Crm\Context\Data\Actor;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Packman\Attributes\Internal;

#[Internal]
final readonly class CompanyCreated implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(
        public Company $company,
        public Actor $actor,
    ) {}
}
