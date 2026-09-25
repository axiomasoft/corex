<?php

declare(strict_types=1);

namespace CoreX\Transfer\Internal\Transfer;

use CoreX\Transfer\Contracts\CancellationSignal;
use Override;

final class NeverCancelled implements CancellationSignal
{
    #[Override]
    public function isCancelled(): bool
    {
        return false;
    }
}
