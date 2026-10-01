<?php

declare(strict_types=1);

namespace CoreX\Workflow\Data;

use CoreX\Attributes\Api;
use CoreX\Context\Data\TenantContext;
use CoreX\Data\Data\JsonDocument;
use DateTimeImmutable;

#[Api(since: '1.0')]
final readonly class SignalRequest
{
    /** Immutable JSON value. */
    public function __construct(public TenantContext $context, public string $name, public string $correlationKey, public JsonDocument $payload, public string $requestKey, public DateTimeImmutable $expiresAt) {}
}
