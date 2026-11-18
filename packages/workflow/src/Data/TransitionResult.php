<?php

declare(strict_types=1);

namespace CoreX\Workflow\Data;

use Packman\Attributes\Api;

#[Api(since: '1.0')]
final readonly class TransitionResult
{
    public function __construct(public string $status) {}
}
