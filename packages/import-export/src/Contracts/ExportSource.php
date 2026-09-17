<?php

declare(strict_types=1);

namespace CoreX\ImportExport\Contracts;

use CoreX\ImportExport\Support\Export\ExportCursor;
use CoreX\ImportExport\Support\Export\ExportManifest;
use CoreX\ImportExport\Support\Export\ExportPage;
use CoreX\ImportExport\Support\TransferLimits;
use CoreX\Support\Data\DataScope;

interface ExportSource
{
    public function page(DataScope $scope, ExportManifest $manifest, ExportCursor $after, TransferLimits $limits): ExportPage;
}
