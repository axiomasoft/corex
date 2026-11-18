<?php

declare(strict_types=1);

namespace CoreX\Tenancy\Runtime\Events;

use Packman\Attributes\Api;

/** Manual initialize/setWorkspace notification after Tenant entered. Protected runFor uses Tenant observations. Listeners must not switch context. */
#[Api(since: '1.0')]
final readonly class TenantContextInitialized
{
    public function __construct(public string $accountId) {}
}
