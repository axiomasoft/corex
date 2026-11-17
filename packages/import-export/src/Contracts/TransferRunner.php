<?php

declare(strict_types=1);

namespace CoreX\ImportExport\Contracts;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use CoreX\Data\Data\DataScope;
use CoreX\ImportExport\Data\StepToken;
use CoreX\ImportExport\Data\TransferReceipt;
use CoreX\ImportExport\Data\TransferRef;

#[Spi(kind: SpiKind::Implement, since: '1.0')]
interface TransferRunner
{
    public function step(DataScope $scope, TransferRef $transfer, StepToken $token): TransferReceipt;
}
