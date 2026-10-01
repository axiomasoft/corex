<?php

declare(strict_types=1);

namespace CoreX\Tasks\Planning\Data;

use CoreX\Attributes\Api;

#[Api(since: '1.0')]
final readonly class PlanningToken
{
    public function __construct(
        public string $requestKey,
        public string $payloadHash,
    ) {}
}
