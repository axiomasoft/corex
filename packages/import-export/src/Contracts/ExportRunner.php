<?php

declare(strict_types=1);

namespace CoreX\ImportExport\Contracts;

use CoreX\Data\Data\DataScope;
use CoreX\ImportExport\Data\Export\ExportCursor;
use CoreX\ImportExport\Data\Export\ExportManifest;
use Packman\Attributes\Spi;
use Packman\Attributes\SpiKind;

#[Spi(kind: SpiKind::Implement, since: '1.0')]
interface ExportRunner
{
    public function seal(DataScope $scope, string $jobId, ExportManifest $draft, ExportCursor $after): ExportManifest;

    public function emit(DataScope $scope, ExportManifest $manifest, ExportCursor $cursor): string;
}
