<?php

declare(strict_types=1);

namespace CoreX\Tenancy\Membership\Data;

use CoreX\Tenancy\Membership\Exceptions\CentralProtocolViolation;
use CoreX\Tenancy\Membership\Internal\Transport\CentralValue;
use DateTimeImmutable;
use Packman\Attributes\Api;

#[Api(since: '1.0')]
final readonly class RevokeMembershipReceipt
{
    public function __construct(
        string $profileVersion,
        string $requestId,
        string $identityId,
        string $accountId,
        string $product,
        public string $commitRef,
        string $revision,
        string $revokeGeneration,
        public DateTimeImmutable $committedAt,
    ) {
        $this->profileVersion = CentralValue::profile(value: $profileVersion);
        $this->requestId = CentralValue::uuid(value: $requestId, field: 'request_id');
        $this->identityId = CentralValue::uuid(value: $identityId, field: 'identity_id');
        $this->accountId = CentralValue::uuid(value: $accountId, field: 'account_id');
        $this->product = CentralValue::product(value: $product);
        $this->revision = CentralValue::counter(value: $revision);
        $this->revokeGeneration = CentralValue::counter(value: $revokeGeneration);

        if (preg_match('/^[a-zA-Z0-9._:-]{1,128}$/D', $this->commitRef) !== 1) {
            throw new CentralProtocolViolation('Invalid opaque commit reference.');
        }
    }

    public string $profileVersion;

    public string $requestId;

    public string $identityId;

    public string $accountId;

    public string $product;

    public string $revision;

    public string $revokeGeneration;
}
