<?php

declare(strict_types=1);

namespace CoreX\Transfer\Contracts;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use CoreX\Data\Data\DataScope;
use CoreX\Transfer\Data\Export\ExportCursor;
use CoreX\Transfer\Data\Export\ExportManifest;
use CoreX\Transfer\Data\Export\ExportPage;
use CoreX\Transfer\Data\TransferLimits;

#[Spi(kind: SpiKind::Implement, since: '1.0')]
interface ExportSource
{
    public function page(DataScope $scope, ExportManifest $manifest, ExportCursor $after, TransferLimits $limits): ExportPage;
}
