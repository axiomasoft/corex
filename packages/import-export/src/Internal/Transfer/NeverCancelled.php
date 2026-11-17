<?php

declare(strict_types=1);

namespace CoreX\ImportExport\Internal\Transfer;

use CoreX\ImportExport\Contracts\CancellationSignal;
use Override;

final class NeverCancelled implements CancellationSignal
{
    #[Override]
    public function isCancelled(): bool
    {
        return false;
    }
}
