<?php

declare(strict_types=1);

namespace CoreX\Observability;

use CoreX\Contracts\OperationObserver;

final class NullOperationObserver implements OperationObserver
{
    public function started(OperationStarted $event): void {}

    public function completed(OperationCompleted $event): void {}

    public function failed(OperationFailed $event): void {}
}
