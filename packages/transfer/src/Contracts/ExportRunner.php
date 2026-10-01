<?php

declare(strict_types=1);

namespace CoreX\Transfer\Contracts;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use CoreX\Data\Data\DataScope;
use CoreX\Transfer\Data\Export\ExportCursor;
use CoreX\Transfer\Data\Export\ExportManifest;

#[Spi(kind: SpiKind::Implement, since: '1.0')]
interface ExportRunner
{
    public function seal(DataScope $scope, string $jobId, ExportManifest $draft, ExportCursor $after): ExportManifest;

    public function emit(DataScope $scope, ExportManifest $manifest, ExportCursor $cursor): string;
}
