<?php

declare(strict_types=1);

namespace CoreX\Tenancy\Contracts;

use CoreX\Tenancy\Membership\Internal\Transport\MembershipQuery;
use CoreX\Tenancy\Membership\Internal\Transport\MembershipSnapshot;
use CoreX\Tenancy\Membership\Internal\Transport\RevokeMembershipReceipt;
use CoreX\Tenancy\Membership\Internal\Transport\RevokeMembershipRequest;

interface CentralApiClient
{
    public function revokeMembership(RevokeMembershipRequest $request): RevokeMembershipReceipt;

    public function membershipFor(MembershipQuery $query): MembershipSnapshot;
}
