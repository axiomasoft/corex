<?php

declare(strict_types=1);

namespace CoreX\Operations\Idempotency\Internal;

use CoreX\Operations\Errors\Exceptions\ApiError;

/** Mutable holder through which a transaction callback reports a handler failure to its caller. */
final class HandlerFailure
{
    public ?ApiError $error = null;
}
