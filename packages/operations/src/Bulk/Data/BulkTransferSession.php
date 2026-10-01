<?php

declare(strict_types=1);

namespace CoreX\Operations\Bulk\Data;

use CoreX\Attributes\Api;
use CoreX\Transfer\Contracts\CancellationSignal;
use CoreX\Transfer\Contracts\ExportRunner;
use CoreX\Transfer\Contracts\TransferResultReader;
use CoreX\Transfer\Contracts\TransferRunner;
use CoreX\Transfer\Data\Export\ExportCursor;
use CoreX\Transfer\Data\Export\ExportManifest;
use CoreX\Transfer\Data\StepToken;
use CoreX\Transfer\Data\TransferRef;
use InvalidArgumentException;

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
