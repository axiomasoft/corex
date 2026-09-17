<?php

declare(strict_types=1);

namespace CoreX\Search\Contracts;

use CoreX\Search\Support\IndexBatch;
use CoreX\Search\Support\IndexOutcome;
use CoreX\Search\Support\IndexSubmission;
use CoreX\Search\Support\IndexSwap;

interface IndexDriver
{
    public function submit(IndexBatch $batch): IndexSubmission;

    public function status(IndexSubmission $submission): IndexOutcome;

    public function swap(IndexSwap $swap): IndexSubmission;
}
