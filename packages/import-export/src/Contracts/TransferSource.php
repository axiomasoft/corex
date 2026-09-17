<?php

declare(strict_types=1);

namespace CoreX\ImportExport\Contracts;

use CoreX\ImportExport\Support\RowBatch;
use CoreX\ImportExport\Support\SourceCursor;
use CoreX\ImportExport\Support\SourceManifest;
use CoreX\ImportExport\Support\TransferLimits;
use CoreX\Support\Data\DataScope;

interface TransferSource
{
    public function describe(DataScope $scope): SourceManifest;

    public function read(SourceManifest $source, SourceCursor $after, TransferLimits $limits): RowBatch;
}
