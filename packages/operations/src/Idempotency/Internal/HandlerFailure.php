<?php

declare(strict_types=1);

namespace CoreX\Operations\Idempotency\Internal;

use CoreX\Operations\Errors\Exceptions\ApiError;
use Packman\Attributes\Internal;

/** Mutable holder through which a transaction callback reports a handler failure to its caller. */
#[Internal]
final class HandlerFailure
{
    public ?ApiError $error = null;
}
