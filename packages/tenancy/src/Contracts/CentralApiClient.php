<?php

declare(strict_types=1);

namespace CoreX\Tenancy\Contracts;

use CoreX\Tenancy\Membership\Data\MembershipQuery;
use CoreX\Tenancy\Membership\Data\MembershipSnapshot;
use CoreX\Tenancy\Membership\Data\RevokeMembershipReceipt;
use CoreX\Tenancy\Membership\Data\RevokeMembershipRequest;
use Packman\Attributes\Spi;
use Packman\Attributes\SpiKind;

#[Spi(kind: SpiKind::Implement, since: '1.0')]
interface CentralApiClient
{
    public function revokeMembership(RevokeMembershipRequest $request): RevokeMembershipReceipt;

    public function membershipFor(MembershipQuery $query): MembershipSnapshot;
}
