<?php

declare(strict_types=1);

namespace CoreX\Workflow\Data;

use CoreX\Attributes\Api;

#[Api(since: '1.0')]
final readonly class SignalResult
{
    public function __construct(public string $status) {}
}
