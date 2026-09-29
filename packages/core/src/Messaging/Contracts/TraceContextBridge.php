<?php

declare(strict_types=1);

namespace CoreX\Messaging\Contracts;

use Closure;
use CoreX\Observability\Internal\OperationContext;

interface TraceContextBridge
{
    /**
     * @param  array<string, scalar|null>  $metadata
     * @return array<string, scalar|null>
     */
    public function inject(array $metadata): array;

    /**
     * Run one consumer operation with producer context linked at span creation.
     *
     * @template T
     *
     * @param  array<string, scalar|null>  $metadata
     * @param  Closure(): T  $work
     * @return T
     */
    public function linkedConsumer(
        array $metadata,
        OperationContext $operation,
        Closure $work,
    ): mixed;
}
