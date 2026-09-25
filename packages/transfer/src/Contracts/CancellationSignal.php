<?php

declare(strict_types=1);

namespace CoreX\Transfer\Contracts;

interface CancellationSignal
{
    public function isCancelled(): bool;
}
