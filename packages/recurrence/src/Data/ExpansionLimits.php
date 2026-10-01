<?php

declare(strict_types=1);

namespace CoreX\Recurrence\Data;

use CoreX\Attributes\Api;
use InvalidArgumentException;

#[Api(since: '1.0')]
final readonly class ExpansionLimits
{
    public function __construct(
        public int $rangeDays = 366,
        public int $emitted = 1000,
        public int $candidateSteps = 100000,
        public int $wallSeconds = 2,
        public int $memoryMiB = 32,
        public int $ruleBytes = 8192,
        public int $exceptionCount = 10000,
    ) {
        if (
            $rangeDays < 1
            || $emitted < 1
            || $emitted > 1000
            || $candidateSteps < $emitted
            || $candidateSteps > 100000
            || $wallSeconds < 1
            || $memoryMiB < 1
            || $ruleBytes < 1
            || $exceptionCount < 1
        ) {
            throw new InvalidArgumentException('The recurrence limits are outside the bounded profile.');
        }
    }
}
