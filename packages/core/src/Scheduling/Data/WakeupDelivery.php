<?php

declare(strict_types=1);

namespace CoreX\Scheduling\Data;

use CoreX\Context\Data\TenantContext;
use Packman\Attributes\Api;

#[Api(since: '2.0.0')]
final readonly class WakeupDelivery
{
    public function __construct(
        public WakeupRequest $request,
        public TenantContext $context,
        public int $generation,
        public int $attempt,
        public string $idempotencyKey,
    ) {}
}
