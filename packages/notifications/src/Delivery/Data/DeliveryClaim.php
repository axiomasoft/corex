<?php

declare(strict_types=1);

namespace CoreX\Notifications\Delivery\Data;

use CoreX\Context\Data\StoredWorkProof;
use CoreX\Data\Data\DataScope;
use CoreX\Data\Data\JsonDocument;
use DateTimeImmutable;
use Packman\Attributes\Api;

#[Api(since: '1.0')]
final readonly class DeliveryClaim
{
    public function __construct(
        public string $id,
        public ?string $notificationId,
        public ?string $digestRunId,
        public string $channel,
        public string $deliveryKey,
        public int $epoch,
        public int $attempts,
        public JsonDocument $message,
        public string $messageHash,
        public string $templateVersion,
        public string $policyVersion,
        public string $driverVersion,
        public DataScope $scope,
        public StoredWorkProof $proof,
        public string $token,
        public DateTimeImmutable $deadline,
        public ?string $providerNamespace,
        public ?string $targetReference,
    ) {}
}
