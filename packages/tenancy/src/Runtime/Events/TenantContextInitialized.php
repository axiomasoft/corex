<?php

declare(strict_types=1);

namespace CoreX\Tenancy\Runtime\Events;

use CoreX\Attributes\Api;

/** Dispatched after every bootstrapper switched to the account. Listeners must not start a context switch. */
#[Api(since: '1.0')]
final readonly class TenantContextInitialized
{
    public function __construct(public string $accountId) {}
}
