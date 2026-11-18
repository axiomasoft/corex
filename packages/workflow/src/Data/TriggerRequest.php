<?php

declare(strict_types=1);

namespace CoreX\Workflow\Data;

use CoreX\Context\Data\TenantContext;
use CoreX\Data\Data\JsonDocument;
use Packman\Attributes\Api;

#[Api(since: '1.0')]
final readonly class TriggerRequest
{
    /** Immutable JSON value. */
    public function __construct(public TenantContext $context, public string $module, public string $entityHandle, public string $eventId, public string $eventName, public string $subjectRef, public string $definitionRef, public string $versionRef, public JsonDocument $scalarSnapshot, public string $requestKey) {}
}
