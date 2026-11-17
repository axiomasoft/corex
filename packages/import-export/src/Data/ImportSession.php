<?php

declare(strict_types=1);

namespace CoreX\ImportExport\Data;

use CoreX\Attributes\Api;
use CoreX\ImportExport\Contracts\CancellationSignal;
use CoreX\ImportExport\Contracts\TransferResultReader;
use CoreX\ImportExport\Contracts\TransferRunner;

#[Api(since: '1.0')]
final readonly class ImportSession
{
    public function __construct(
        public TransferRunner $runner,
        public TransferRef $transfer,
        public StepToken $token,
        public TransferResultReader $results,
        public CancellationSignal $cancellation,
    ) {}
}
