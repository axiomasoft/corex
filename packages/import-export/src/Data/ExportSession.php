<?php

declare(strict_types=1);

namespace CoreX\ImportExport\Data;

use CoreX\Attributes\Api;
use CoreX\ImportExport\Contracts\CancellationSignal;
use CoreX\ImportExport\Contracts\ExportRunner;
use CoreX\ImportExport\Contracts\TransferResultReader;
use CoreX\ImportExport\Data\Export\ExportCursor;
use CoreX\ImportExport\Data\Export\ExportManifest;

#[Api(since: '1.0')]
final readonly class ExportSession
{
    public function __construct(
        public ExportRunner $runner,
        public TransferRef $transfer,
        public StepToken $token,
        public TransferResultReader $results,
        public CancellationSignal $cancellation,
        public ExportManifest $manifest,
        public ExportCursor $cursor,
    ) {}
}
