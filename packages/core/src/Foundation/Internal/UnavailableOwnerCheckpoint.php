<?php

declare(strict_types=1);

namespace CoreX\Foundation\Internal;

use CoreX\Foundation\Data\LifecycleBlocker;
use Packman\Attributes\Internal;
use RuntimeException;
use Throwable;

#[Internal]
final class UnavailableOwnerCheckpoint extends RuntimeException
{
    public function __construct(public readonly LifecycleBlocker $blocker, Throwable $cause)
    {
        parent::__construct('The named owner checkpoint requires reconciliation.', previous: $cause);
    }
}
