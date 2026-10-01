<?php

declare(strict_types=1);

namespace CoreX\Search\Contracts;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use CoreX\Search\Data\IndexBatch;
use CoreX\Search\Data\IndexOutcome;
use CoreX\Search\Data\IndexSubmission;
use CoreX\Search\Data\IndexSwap;

#[Spi(kind: SpiKind::Implement, since: '1.0')]
interface IndexDriver
{
    public function submit(IndexBatch $batch): IndexSubmission;

    public function status(IndexSubmission $submission): IndexOutcome;

    public function swap(IndexSwap $swap): IndexSubmission;
}
