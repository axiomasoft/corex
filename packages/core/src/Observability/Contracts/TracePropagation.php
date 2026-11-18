<?php

declare(strict_types=1);

namespace CoreX\Observability\Contracts;

use Closure;
use CoreX\Observability\Data\OperationContext;
use Packman\Attributes\Spi;
use Packman\Attributes\SpiKind;

/** Call port for the methods consumed across the owning module boundary. */
#[Spi(kind: SpiKind::Call, since: '1.0')]
interface TracePropagation
{
    /**
     * @param  array<string, scalar|null>  $metadata
     * @return array<string, scalar|null>
     */
    public function inject(array $metadata): array;

    /**
     * @template T
     *
     * @param  array<string, scalar|null>  $metadata
     * @param  Closure(): T  $work
     * @return T
     */
    public function linkedConsumer(array $metadata, OperationContext $operation, Closure $work): mixed;
}
