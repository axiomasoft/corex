<?php

declare(strict_types=1);

namespace Flex\Crm\Company\Events;

use Flex\Crm\Company\Models\Company;
use Flex\Crm\Context\Data\Actor;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

final class CompanyCreated implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(
        public readonly Company $company,
        public readonly Actor $actor,
    ) {}
}
