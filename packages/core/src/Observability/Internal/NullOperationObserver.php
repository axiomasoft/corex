<?php

declare(strict_types=1);

namespace CoreX\Observability\Internal;

use CoreX\Observability\Contracts\OperationObserver;
use CoreX\Observability\Data\OperationCompleted;
use CoreX\Observability\Data\OperationFailed;
use CoreX\Observability\Data\OperationStarted;
use Packman\Attributes\Internal;

#[Internal]
final class NullOperationObserver implements OperationObserver
{
    public function started(OperationStarted $event): void {}

    public function completed(OperationCompleted $event): void {}

    public function failed(OperationFailed $event): void {}
}
