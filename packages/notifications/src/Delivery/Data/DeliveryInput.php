<?php

declare(strict_types=1);

namespace CoreX\Notifications\Data;

final readonly class DeliveryInput
{
    /** @param array<string, mixed> $message */
    public function __construct(
        public string $deliveryKey,
        public int $attemptEpoch,
        public DeliveryTarget $target,
        public array $message,
        public NotificationContext $context,
        public string $messageSha256,
        public string $templateVersion,
        public string $policyVersion,
        public string $driverVersion,
    ) {}
}
