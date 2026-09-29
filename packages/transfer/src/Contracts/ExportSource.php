<?php

declare(strict_types=1);

namespace CoreX\Transfer\Contracts;

use CoreX\Data\DataScope;
use CoreX\Transfer\Data\Export\ExportCursor;
use CoreX\Transfer\Data\Export\ExportManifest;
use CoreX\Transfer\Data\Export\ExportPage;
use CoreX\Transfer\Data\TransferLimits;

interface ExportSource
{
    public function page(DataScope $scope, ExportManifest $manifest, ExportCursor $after, TransferLimits $limits): ExportPage;
}
