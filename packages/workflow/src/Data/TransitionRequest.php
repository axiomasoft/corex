<?php

declare(strict_types=1);

namespace CoreX\Workflow\Data;

use CoreX\Context\Data\TenantContext;
use CoreX\Data\Data\JsonDocument;
use Packman\Attributes\Api;

#[Api(since: '1.0')]
final readonly class TransitionRequest
{
    /** Immutable JSON value. */
    public function __construct(public TenantContext $context, public string $subjectRef, public string $edge, public string $actorRef, public JsonDocument $payload, public string $requestKey) {}
}
