<?php

declare(strict_types=1);

namespace CoreX\Notifications\Data;

final readonly class DeliveryClaim
{
    /** @param array<string, mixed> $message */
    public function __construct(
        public string $id,
        public ?string $notificationId,
        public ?string $digestRunId,
        public string $channel,
        public string $deliveryKey,
        public int $epoch,
        public int $attempts,
        public array $message,
        public string $messageHash,
        public string $templateVersion,
        public string $policyVersion,
        public string $driverVersion,
    ) {}
}
