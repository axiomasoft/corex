<?php

declare(strict_types=1);

namespace CoreX\Tenancy\Runtime\Events;

use CoreX\Attributes\Api;

/** Dispatched after the account context ended and every bootstrapper reverted. */
#[Api(since: '1.0')]
final readonly class TenantContextEnded
{
    public function __construct(public string $accountId) {}
}
