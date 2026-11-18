<?php

declare(strict_types=1);

namespace CoreX\Notifications\Inbox\Data;

use CoreX\Context\Data\TenantContext;
use Packman\Attributes\Api;

#[Api(since: '1.0')]
final readonly class NotificationContext
{
    public function __construct(public TenantContext $tenant, public string $actorKind, public ?string $actorId) {}
}
