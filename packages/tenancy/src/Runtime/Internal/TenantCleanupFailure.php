<?php

declare(strict_types=1);

namespace CoreX\Tenancy\Runtime\Internal;

use Packman\Attributes\Internal;
use RuntimeException;
use Throwable;

/** Both causes of a double failure: the original one is `getPrevious()`, the cleanup one a property. */
#[Internal]
final class TenantCleanupFailure extends RuntimeException
{
    public function __construct(Throwable $original, public readonly Throwable $cleanup)
    {
        parent::__construct('Tenant runtime cleanup failed; application must be recreated', 0, $original);
    }
}
