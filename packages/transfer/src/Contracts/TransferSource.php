<?php

declare(strict_types=1);

namespace CoreX\Transfer\Contracts;

use CoreX\Support\Data\DataScope;
use CoreX\Transfer\Data\RowBatch;
use CoreX\Transfer\Data\SourceCursor;
use CoreX\Transfer\Data\SourceManifest;
use CoreX\Transfer\Data\TransferLimits;

interface TransferSource
{
    public function describe(DataScope $scope): SourceManifest;

    public function read(SourceManifest $source, SourceCursor $after, TransferLimits $limits): RowBatch;
}
