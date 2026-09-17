<?php

declare(strict_types=1);

namespace CoreX\Workflow\Data;

final readonly class TransitionResult
{
    public function __construct(public string $status) {}
}
