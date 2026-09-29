<?php

declare(strict_types=1);

namespace CoreX\Observability\Contracts;

use CoreX\Observability\Data\OperationCompleted;
use CoreX\Observability\Data\OperationFailed;
use CoreX\Observability\Data\OperationStarted;

interface OperationObserver
{
    public function started(OperationStarted $event): void;

    public function completed(OperationCompleted $event): void;

    public function failed(OperationFailed $event): void;
}
