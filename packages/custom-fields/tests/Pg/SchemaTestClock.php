<?php

declare(strict_types=1);

namespace CoreX\CustomFields\Tests\Pg;

use DateTimeImmutable;
use Psr\Clock\ClockInterface;

final class SchemaTestClock implements ClockInterface
{
    public int $offset = 0;

    public function now(): DateTimeImmutable
    {
        return (new DateTimeImmutable('now'))->modify('+'.$this->offset.' seconds');
    }
}
