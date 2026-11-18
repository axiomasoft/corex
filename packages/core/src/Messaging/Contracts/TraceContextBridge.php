<?php

declare(strict_types=1);

namespace CoreX\Messaging\Contracts;

use Closure;
use CoreX\Observability\Data\OperationContext;
use Packman\Attributes\Spi;
use Packman\Attributes\SpiKind;

#[Spi(kind: SpiKind::Implement, since: '1.0')]
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
