<?php

declare(strict_types=1);

namespace CoreX\Workflow\Data;

use CoreX\Context\Data\TenantContext;

final readonly class TriggerRequest
{
    /** @param array<string, mixed> $scalarSnapshot */
    public function __construct(
        public TenantContext $context,
        public string $module,
        public string $entityHandle,
        public string $eventId,
        public string $eventName,
        public string $subjectRef,
        public string $definitionRef,
        public string $versionRef,
        public array $scalarSnapshot,
        public string $requestKey,
    ) {}
}
