<?php

declare(strict_types=1);

namespace CoreX\Tasks\Data;

final readonly class PlanningToken
{
    public function __construct(
        public string $requestKey,
        public string $payloadHash,
    ) {}
}
