<?php

declare(strict_types=1);

namespace CoreX\Notifications\Data;

use CoreX\Tenancy\TenantContext;

final readonly class NotificationContext
{
    public function __construct(public TenantContext $tenant, public string $actorKind, public ?string $actorId) {}
}
