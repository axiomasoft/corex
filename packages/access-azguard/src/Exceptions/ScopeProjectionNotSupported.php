<?php

declare(strict_types=1);

namespace CoreX\AccessAzguard\Exceptions;

use CoreX\Attributes\Api;
use LogicException;

/** AzGuard policies cannot be represented by the legacy finite Scope enum. */
#[Api(since: '1.0')]
final class ScopeProjectionNotSupported extends LogicException
{
    public function __construct()
    {
        parent::__construct('AzGuard policies have no lossless CoreX Scope projection. Use AccessChecker::canOnRecord() or ScopeResolver::apply().');
    }
}
