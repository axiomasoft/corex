<?php

declare(strict_types=1);

namespace CoreX\Operations\Bulk\Data;

use CoreX\ImportExport\Contracts\CancellationSignal;
use CoreX\ImportExport\Contracts\ExportRunner;
use CoreX\ImportExport\Contracts\TransferResultReader;
use CoreX\ImportExport\Contracts\TransferRunner;
use CoreX\ImportExport\Data\Export\ExportCursor;
use CoreX\ImportExport\Data\Export\ExportManifest;
use CoreX\ImportExport\Data\StepToken;
use CoreX\ImportExport\Data\TransferRef;
use InvalidArgumentException;
use Packman\Attributes\Api;

#[Api(since: '1.0')]
final readonly class BulkTransferSession
{
    public function __construct(
        public TransferRunner|ExportRunner $runner,
        public TransferRef $transfer,
        public StepToken $token,
        public CancellationSignal $cancellation,
        public TransferResultReader $results,
        public ?ExportManifest $manifest = null,
        public ?ExportCursor $cursor = null,
    ) {
        if ($runner instanceof ExportRunner && ($manifest === null || $cursor === null)) {
            throw new InvalidArgumentException('Export bulk sessions require a manifest and cursor.');
        }

        if ($runner instanceof TransferRunner && ($manifest !== null || $cursor !== null)) {
            throw new InvalidArgumentException('Import bulk sessions do not carry an export manifest.');
        }
    }
}
