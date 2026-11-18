<?php

declare(strict_types=1);

namespace CoreX\Scheduling\Data;

use DateTimeImmutable;
use Packman\Attributes\Api;

#[Api(since: '1.0')]
final readonly class SweepResult
{
    public function __construct(public int $claimed, public int $completed, public int $retried, public int $failed, public int $skipped, public bool $remainingDue, public ?DateTimeImmutable $nextFireAt) {}
}
