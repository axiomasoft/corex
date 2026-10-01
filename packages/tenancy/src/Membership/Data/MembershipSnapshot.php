<?php

declare(strict_types=1);

namespace CoreX\Tenancy\Membership\Data;

use CoreX\Attributes\Api;
use CoreX\Tenancy\Membership\Enums\MembershipState;
use CoreX\Tenancy\Membership\Exceptions\CentralProtocolViolation;
use CoreX\Tenancy\Membership\Internal\Transport\CentralValue;
use DateTimeImmutable;

#[Api(since: '1.0')]
final readonly class MembershipSnapshot
{
    public function __construct(
        string $profileVersion,
        string $requestId,
        string $identityId,
        string $accountId,
        string $product,
        ?string $membershipId,
        MembershipState $state,
        string $revision,
        string $revokeGeneration,
        DateTimeImmutable $readStartedAt,
        DateTimeImmutable $observedAt,
    ) {
        $this->profileVersion = CentralValue::profile(value: $profileVersion);
        $this->requestId = CentralValue::uuid(value: $requestId, field: 'request_id');
        $this->identityId = CentralValue::uuid(value: $identityId, field: 'identity_id');
        $this->accountId = CentralValue::uuid(value: $accountId, field: 'account_id');
        $this->product = CentralValue::product(value: $product);
        $this->membershipId = $membershipId === null ? null : CentralValue::uuid(value: $membershipId, field: 'membership_id');
        $this->state = $state;
        $this->revision = CentralValue::counter(value: $revision);
        $this->revokeGeneration = CentralValue::counter(value: $revokeGeneration);
        $this->readStartedAt = $readStartedAt;
        $this->observedAt = $observedAt;

        if ($this->state === MembershipState::Active && $this->membershipId === null || $this->observedAt < $this->readStartedAt) {
            throw new CentralProtocolViolation('Invalid membership authority snapshot.');
        }
    }

    public string $profileVersion;

    public string $requestId;

    public string $identityId;

    public string $accountId;

    public string $product;

    public ?string $membershipId;

    public MembershipState $state;

    public string $revision;

    public string $revokeGeneration;

    public DateTimeImmutable $readStartedAt;

    public DateTimeImmutable $observedAt;
}
