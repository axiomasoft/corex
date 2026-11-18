<?php

declare(strict_types=1);

namespace CoreX\Scheduling\Data;

use Packman\Attributes\Api;

#[Api(since: '1.0')]
final readonly class ScheduleResult
{
    public function __construct(public string $status, public string $requestKey) {}
}
