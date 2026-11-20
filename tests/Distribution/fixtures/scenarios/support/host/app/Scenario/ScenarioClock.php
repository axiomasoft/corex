<?php

declare(strict_types=1);

namespace App\Scenario;

use DateTimeImmutable;
use Psr\Clock\ClockInterface;

final class ScenarioClock implements ClockInterface
{
    private DateTimeImmutable $instant;

    public function __construct()
    {
        $this->instant = new DateTimeImmutable('2026-11-20T00:00:00Z');
    }

    public function now(): DateTimeImmutable
    {
        return $this->instant;
    }

    public function advance(int $seconds): void
    {
        $this->instant = $this->instant->modify('+'.$seconds.' seconds');
    }
}
