<?php

declare(strict_types=1);

namespace CoreX\Notifications\Data;

final readonly class DeliveryOutcome
{
    public function __construct(public string $kind, public ?string $providerRef, public ?int $retryAfter, public string $reason) {}
}
