<?php

declare(strict_types=1);

namespace CoreX\Workflow\Data;

use CoreX\Context\Data\TenantContext;
use Packman\Attributes\Api;

/** A pinned definition for a trigger in the current trusted tenant context. */
#[Api(since: '2.0.0')]
final readonly class DefinitionRequest
{
    public function __construct(
        public TenantContext $context,
        public string $module,
        public string $entityHandle,
        public string $eventName,
        public string $definitionRef,
        public string $versionRef,
    ) {}
}
