<?php

declare(strict_types=1);

namespace CoreX\Messaging\Internal;

use Closure;
use CoreX\Messaging\Contracts\TraceContextBridge;
use CoreX\Observability\Data\OperationContext;

final class NullTraceContextBridge implements TraceContextBridge
{
    public function inject(array $metadata): array
    {
        return $metadata;
    }

    public function linkedConsumer(
        array $metadata,
        OperationContext $operation,
        Closure $work,
    ): mixed {
        return $work();
    }
}
