<?php

declare(strict_types=1);

namespace CoreX\Observability\Contracts;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use CoreX\Observability\Data\OperationCompleted;
use CoreX\Observability\Data\OperationFailed;
use CoreX\Observability\Data\OperationStarted;

#[Spi(kind: SpiKind::Implement, since: '1.0')]
interface OperationObserver
{
    public function started(OperationStarted $event): void;

    public function completed(OperationCompleted $event): void;

    public function failed(OperationFailed $event): void;
}
