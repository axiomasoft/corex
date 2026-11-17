<?php

declare(strict_types=1);

namespace CoreX\ImportExport\Contracts;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use CoreX\Data\Data\DataScope;
use CoreX\ImportExport\Data\Export\ExportCursor;
use CoreX\ImportExport\Data\Export\ExportManifest;
use CoreX\ImportExport\Data\Export\ExportPage;
use CoreX\ImportExport\Data\TransferLimits;

#[Spi(kind: SpiKind::Implement, since: '1.0')]
interface ExportSource
{
    public function page(DataScope $scope, ExportManifest $manifest, ExportCursor $after, TransferLimits $limits): ExportPage;
}
