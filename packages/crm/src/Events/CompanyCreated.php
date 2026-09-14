<?php

declare(strict_types=1);

namespace Flex\Crm\Events;

use Flex\Crm\Data\Actor;
use Flex\Crm\Models\Company;
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
