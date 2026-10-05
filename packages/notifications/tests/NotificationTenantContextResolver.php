<?php

declare(strict_types=1);

namespace CoreX\Notifications\Tests;

use CoreX\Context\Contracts\TenantContextResolver;
use CoreX\Context\Data\AccountRef;
use CoreX\Context\Data\TenantContext;

final class NotificationTenantContextResolver implements TenantContextResolver
{
    public function current(): TenantContext
    {
        return new TenantContext(new AccountRef('11111111-1111-7111-8111-111111111111', 'notification-test', 'active', [], []));
    }
}
