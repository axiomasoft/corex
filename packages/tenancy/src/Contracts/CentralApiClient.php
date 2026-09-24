<?php

declare(strict_types=1);

namespace CoreX\Tenancy\Contracts;

use CoreX\Tenancy\Membership\Transport\MembershipQuery;
use CoreX\Tenancy\Membership\Transport\MembershipSnapshot;
use CoreX\Tenancy\Membership\Transport\RevokeMembershipReceipt;
use CoreX\Tenancy\Membership\Transport\RevokeMembershipRequest;

interface CentralApiClient
{
    public function revokeMembership(RevokeMembershipRequest $request): RevokeMembershipReceipt;

    public function membershipFor(MembershipQuery $query): MembershipSnapshot;
}
