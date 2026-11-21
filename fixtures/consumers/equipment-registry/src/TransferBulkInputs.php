<?php

declare(strict_types=1);

namespace Equipment\Registry;

use CoreX\ImportExport\Contracts\ExportSource;
use CoreX\ImportExport\Contracts\RecordMapper;
use CoreX\ImportExport\Contracts\TransferSink;
use CoreX\ImportExport\Contracts\TransferSource;
use CoreX\ImportExport\Data\ConflictRule;
use CoreX\ImportExport\Data\Export\ExportCursor;
use CoreX\ImportExport\Data\Export\ExportManifest;
use CoreX\ImportExport\Data\MappingSnapshot;
use CoreX\Operations\Bulk\Data\BulkContext;

interface TransferBulkInputs
{
    /** Resolve and authorize the immutable input under the current scope without opening a session.
     * @return array{source: TransferSource, mapper: RecordMapper, sink: TransferSink, mapping: MappingSnapshot, conflicts: ConflictRule, fingerprint: string, dryRun: bool}
     */
    public function import(BulkContext $context): array;

    /** Resolve and authorize the current export source and projection.
     * @return array{source: ExportSource, manifest: ExportManifest, cursor: ExportCursor, fingerprint: string}
     */
    public function export(BulkContext $context): array;
}
