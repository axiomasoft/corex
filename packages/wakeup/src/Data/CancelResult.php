<?php

declare(strict_types=1);

namespace CoreX\Wakeup\Data;

final readonly class CancelResult
{
    public function __construct(public string $status) {}
}
