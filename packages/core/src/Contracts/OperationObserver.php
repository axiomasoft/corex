<?php

declare(strict_types=1);

namespace CoreX\Contracts;

use CoreX\Observability\OperationCompleted;
use CoreX\Observability\OperationFailed;
use CoreX\Observability\OperationStarted;

interface OperationObserver
{
    public function started(OperationStarted $event): void;

    public function completed(OperationCompleted $event): void;

    public function failed(OperationFailed $event): void;
}
