<?php

declare(strict_types=1);

namespace CoreX\Tenancy\Membership\Events;

use CoreX\Tenancy\Membership\Data\ActorRef;
use CoreX\Tenancy\Membership\Enums\MembershipReason;
use CoreX\Tenancy\Membership\Internal\Transport\CentralValue;
use DateTimeImmutable;
use Packman\Attributes\Spi;
use Packman\Attributes\SpiKind;

#[Spi(kind: SpiKind::Call, since: '1.0')]
final readonly class MembershipRevoked
{
    public function __construct(
        string $identityId,
        string $accountId,
        string $membershipId,
        public MembershipReason $reason,
        public ActorRef $actor,
        public DateTimeImmutable $occurredAt,
    ) {
        $this->identityId = CentralValue::uuid(value: $identityId, field: 'identity_id');
        $this->accountId = CentralValue::uuid(value: $accountId, field: 'account_id');
        $this->membershipId = CentralValue::uuid(value: $membershipId, field: 'membership_id');
    }

    public string $identityId;

    public string $accountId;

    public string $membershipId;
}
