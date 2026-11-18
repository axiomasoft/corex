<?php

declare(strict_types=1);

namespace CoreX\Observability\Contracts;

use CoreX\Observability\Data\OperationCompleted;
use CoreX\Observability\Data\OperationFailed;
use CoreX\Observability\Data\OperationStarted;
use Packman\Attributes\Spi;
use Packman\Attributes\SpiKind;

#[Spi(kind: SpiKind::Implement, since: '1.0')]
interface OperationObserver
{
    public function started(OperationStarted $event): void;

    public function completed(OperationCompleted $event): void;

    public function failed(OperationFailed $event): void;
}
