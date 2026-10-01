<?php

declare(strict_types=1);

namespace CoreX\Tenancy\Membership\Laravel;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use CoreX\Tenancy\Membership\Data\RevokeMembershipReceipt;
use CoreX\Tenancy\Membership\Internal\Transport\CentralValue;

#[Spi(kind: SpiKind::Call, since: '1.0')]
final class RevokeMembershipReceiptFactory
{
    /** @param array<string, mixed> $payload */
    public static function fromPayload(array $payload): RevokeMembershipReceipt
    {
        CentralValue::fields(payload: $payload, fields: ['profile_version', 'request_id', 'identity_id', 'account_id', 'product', 'commit_ref', 'revision', 'revoke_generation', 'committed_at']);

        return new RevokeMembershipReceipt(profileVersion: CentralValue::string(payload: $payload, field: 'profile_version'), requestId: CentralValue::string(payload: $payload, field: 'request_id'), identityId: CentralValue::string(payload: $payload, field: 'identity_id'), accountId: CentralValue::string(payload: $payload, field: 'account_id'), product: CentralValue::string(payload: $payload, field: 'product'), commitRef: CentralValue::string(payload: $payload, field: 'commit_ref'), revision: CentralValue::string(payload: $payload, field: 'revision'), revokeGeneration: CentralValue::string(payload: $payload, field: 'revoke_generation'), committedAt: CentralValue::utc(value: CentralValue::string(payload: $payload, field: 'committed_at')));
    }
}
