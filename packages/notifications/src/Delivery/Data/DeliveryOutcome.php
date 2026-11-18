<?php

declare(strict_types=1);

namespace CoreX\Notifications\Delivery\Data;

use Packman\Attributes\Api;

#[Api(since: '1.0')]
final readonly class DeliveryOutcome
{
    public function __construct(public string $kind, public ?string $providerRef, public ?int $retryAfter, public string $reason) {}
}
