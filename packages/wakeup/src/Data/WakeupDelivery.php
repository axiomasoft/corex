<?php

declare(strict_types=1);

namespace CoreX\Wakeup\Data;

use CoreX\Attributes\Api;

#[Api(since: '1.0')]
final readonly class WakeupDelivery
{
    public function __construct(public WakeupRequest $request, public int $generation, public int $attempt) {}
}
