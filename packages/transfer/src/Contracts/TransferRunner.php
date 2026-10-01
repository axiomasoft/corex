<?php

declare(strict_types=1);

namespace CoreX\Transfer\Contracts;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use CoreX\Data\Data\DataScope;
use CoreX\Transfer\Data\StepToken;
use CoreX\Transfer\Data\TransferReceipt;
use CoreX\Transfer\Data\TransferRef;

#[Spi(kind: SpiKind::Implement, since: '1.0')]
interface TransferRunner
{
    public function step(DataScope $scope, TransferRef $transfer, StepToken $token): TransferReceipt;
}
