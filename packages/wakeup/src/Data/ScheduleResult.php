<?php

declare(strict_types=1);

namespace CoreX\Wakeup\Data;

use CoreX\Attributes\Api;

#[Api(since: '1.0')]
final readonly class ScheduleResult
{
    public function __construct(public string $status, public string $requestKey) {}
}
