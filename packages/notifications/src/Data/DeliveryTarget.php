<?php

declare(strict_types=1);

namespace CoreX\Notifications\Data;

final readonly class DeliveryTarget
{
    public function __construct(public string $channel, public string $reference) {}
}
