<?php

declare(strict_types=1);

namespace CoreX\Wakeup\Data;

final readonly class WakeupDelivery
{
    public function __construct(public WakeupRequest $request, public int $generation, public int $attempt) {}
}
