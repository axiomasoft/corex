<?php

declare(strict_types=1);

namespace CoreX\Wakeup\Data;

use DateTimeImmutable;

final readonly class SweepResult
{
    public function __construct(public int $claimed, public int $completed, public int $retried, public int $failed, public int $skipped, public bool $remainingDue, public ?DateTimeImmutable $nextFireAt) {}
}
