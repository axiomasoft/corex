<?php

declare(strict_types=1);

namespace CoreX\ImportExport\Contracts;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use CoreX\Data\Data\DataScope;
use CoreX\ImportExport\Data\ConflictRule;
use CoreX\ImportExport\Data\Export\ExportCursor;
use CoreX\ImportExport\Data\Export\ExportManifest;
use CoreX\ImportExport\Data\ExportSession;
use CoreX\ImportExport\Data\ImportSession;
use CoreX\ImportExport\Data\MappingSnapshot;
use CoreX\ImportExport\Data\TransferRef;

/** Opens idempotent sessions using package-owned persistence and executors. */
#[Spi(kind: SpiKind::Call, since: '1.0')]
interface ImportExportFactory
{
    public function openImport(
        DataScope $scope,
        TransferRef $transfer,
        TransferSource $source,
        RecordMapper $mapper,
        TransferSink $target,
        MappingSnapshot $mapping,
        ConflictRule $conflicts,
        bool $dryRun = false,
        ?CancellationSignal $cancellation = null,
    ): ImportSession;

    public function openExport(
        DataScope $scope,
        TransferRef $transfer,
        ExportSource $source,
        ExportManifest $manifest,
        ExportCursor $cursor,
        ?CancellationSignal $cancellation = null,
    ): ExportSession;
}
