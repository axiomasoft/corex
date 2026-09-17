<?php

declare(strict_types=1);

namespace CoreX\ImportExport\Contracts;

use CoreX\ImportExport\Support\StepToken;
use CoreX\ImportExport\Support\TransferReceipt;
use CoreX\ImportExport\Support\TransferRef;
use CoreX\Support\Data\DataScope;

interface TransferRunner
{
    public function step(DataScope $scope, TransferRef $transfer, StepToken $token): TransferReceipt;
}
