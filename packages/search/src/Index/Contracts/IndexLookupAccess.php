<?php

declare(strict_types=1);

namespace CoreX\Search\Index\Contracts;

use CoreX\Search\Index\Data\IndexBatch;
use Packman\Attributes\Spi;
use Packman\Attributes\SpiKind;

/** Authorize a read of the provider task for this exact durable batch. */
#[Spi(kind: SpiKind::Call, since: '2.0.0')]
interface IndexLookupAccess
{
    public function assert(IndexBatch $batch): void;
}
