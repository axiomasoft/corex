<?php

declare(strict_types=1);

namespace CoreX\Search\Index\Contracts;

use CoreX\Search\Index\Data\IndexBatch;
use CoreX\Search\Index\Data\IndexReceipt;
use CoreX\Search\Index\Data\IndexTarget;
use CoreX\Search\Index\Enums\IndexOutcome;
use Packman\Attributes\Spi;
use Packman\Attributes\SpiKind;

#[Spi(kind: SpiKind::Implement, since: '2.0.0')]
interface IndexDriver
{
    public function submit(IndexBatch $batch): IndexReceipt;

    /** Resolve this submission on this immutable UID. A lost receipt requires provider-side identity evidence. */
    public function lookup(IndexBatch $batch, ?IndexReceipt $receipt): IndexOutcome;

    public function canary(IndexTarget $target): bool;
}
