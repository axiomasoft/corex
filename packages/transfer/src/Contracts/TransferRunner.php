<?php

declare(strict_types=1);

namespace CoreX\Transfer\Contracts;

use CoreX\Data\DataScope;
use CoreX\Transfer\Data\StepToken;
use CoreX\Transfer\Data\TransferReceipt;
use CoreX\Transfer\Data\TransferRef;

interface TransferRunner
{
    public function step(DataScope $scope, TransferRef $transfer, StepToken $token): TransferReceipt;
}
