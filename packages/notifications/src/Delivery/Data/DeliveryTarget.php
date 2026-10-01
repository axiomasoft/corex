<?php

declare(strict_types=1);

namespace CoreX\Notifications\Delivery\Data;

use CoreX\Attributes\Api;

#[Api(since: '1.0')]
final readonly class DeliveryTarget
{
    public function __construct(public string $channel, public string $reference) {}
}
