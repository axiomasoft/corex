<?php

declare(strict_types=1);

namespace CoreX\Tenancy\Membership\Laravel;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use CoreX\Tenancy\Membership\Data\MembershipSnapshot;
use CoreX\Tenancy\Membership\Enums\MembershipState;
use CoreX\Tenancy\Membership\Exceptions\CentralProtocolViolation;
use CoreX\Tenancy\Membership\Internal\Transport\CentralValue;

#[Spi(kind: SpiKind::Call, since: '1.0')]
final class MembershipSnapshotFactory
{
    /** @param array<string, mixed> $payload */
    public static function fromPayload(array $payload): MembershipSnapshot
    {
        CentralValue::fields(payload: $payload, fields: ['profile_version', 'request_id', 'identity_id', 'account_id', 'product', 'membership_id', 'state', 'revision', 'revoke_generation', 'read_started_at', 'observed_at']);

        if ($payload['membership_id'] !== null && ! is_string($payload['membership_id'])) {
            throw new CentralProtocolViolation('Invalid nullable membership UUID.');
        }
        $state = MembershipState::tryFrom(CentralValue::string(payload: $payload, field: 'state')) ?? throw new CentralProtocolViolation('Unknown membership state.');

        return new MembershipSnapshot(profileVersion: CentralValue::string(payload: $payload, field: 'profile_version'), requestId: CentralValue::string(payload: $payload, field: 'request_id'), identityId: CentralValue::string(payload: $payload, field: 'identity_id'), accountId: CentralValue::string(payload: $payload, field: 'account_id'), product: CentralValue::string(payload: $payload, field: 'product'), membershipId: $payload['membership_id'], state: $state, revision: CentralValue::string(payload: $payload, field: 'revision'), revokeGeneration: CentralValue::string(payload: $payload, field: 'revoke_generation'), readStartedAt: CentralValue::utc(value: CentralValue::string(payload: $payload, field: 'read_started_at')), observedAt: CentralValue::utc(value: CentralValue::string(payload: $payload, field: 'observed_at')));
    }
}
