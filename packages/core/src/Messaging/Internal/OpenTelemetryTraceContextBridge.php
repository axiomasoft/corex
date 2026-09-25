<?php

declare(strict_types=1);

namespace CoreX\Messaging\Internal;

use Closure;
use CoreX\Messaging\Contracts\TraceContextBridge;
use CoreX\Observability\OpenTelemetryBridge;
use CoreX\Observability\OperationContext;

/**
 * Messaging binding for the Foundation OpenTelemetry adapter.
 * SDK names stay in {@see OpenTelemetryBridge}.
 */
final class OpenTelemetryTraceContextBridge implements TraceContextBridge
{
    public function __construct(
        private readonly OpenTelemetryBridge $bridge,
    ) {}

    public function inject(array $metadata): array
    {
        return $this->bridge->inject(metadata: $metadata);
    }

    public function linkedConsumer(array $metadata, OperationContext $operation, Closure $work): mixed
    {
        return $this->bridge->linkedConsumer(metadata: $metadata, operation: $operation, work: $work);
    }
}
