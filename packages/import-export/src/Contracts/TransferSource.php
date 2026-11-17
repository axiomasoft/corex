<?php

declare(strict_types=1);

namespace CoreX\ImportExport\Contracts;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use CoreX\Data\Data\DataScope;
use CoreX\ImportExport\Data\RowBatch;
use CoreX\ImportExport\Data\SourceCursor;
use CoreX\ImportExport\Data\SourceManifest;
use CoreX\ImportExport\Data\TransferLimits;

#[Spi(kind: SpiKind::Implement, since: '1.0')]
interface TransferSource
{
    public function describe(DataScope $scope): SourceManifest;

    public function read(SourceManifest $source, SourceCursor $after, TransferLimits $limits): RowBatch;
}
