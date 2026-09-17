<?php

declare(strict_types=1);

namespace CoreX\Workflow\Data;

final readonly class SignalResult
{
    public function __construct(public string $status) {}
}
