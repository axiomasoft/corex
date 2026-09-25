<?php

declare(strict_types=1);

namespace CoreX\Transfer\Contracts;

use CoreX\Support\Data\DataScope;
use CoreX\Transfer\Data\Export\ExportCursor;
use CoreX\Transfer\Data\Export\ExportManifest;

interface ExportRunner
{
    public function seal(DataScope $scope, string $jobId, ExportManifest $draft, ExportCursor $after): ExportManifest;

    public function emit(DataScope $scope, ExportManifest $manifest, ExportCursor $cursor): string;
}
