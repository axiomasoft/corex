<?php

declare(strict_types=1);

namespace CoreX\Workflow\Data;

use CoreX\Tenancy\TenantContext;
use DateTimeImmutable;

final readonly class SignalRequest
{
    /** @param array<string, mixed> $payload */
    public function __construct(
        public TenantContext $context,
        public string $name,
        public string $correlationKey,
        public array $payload,
        public string $requestKey,
        public DateTimeImmutable $expiresAt,
    ) {}
}
