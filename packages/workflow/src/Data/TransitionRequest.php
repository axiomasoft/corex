<?php

declare(strict_types=1);

namespace CoreX\Workflow\Data;

use CoreX\Tenancy\TenantContext;

final readonly class TransitionRequest
{
    /** @param array<string, mixed> $payload */
    public function __construct(
        public TenantContext $context,
        public string $subjectRef,
        public string $edge,
        public string $actorRef,
        public array $payload,
        public string $requestKey,
    ) {}
}
