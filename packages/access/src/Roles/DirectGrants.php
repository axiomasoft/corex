<?php

declare(strict_types=1);

namespace CoreX\Access\Roles;

use CoreX\Context\Contracts\Principal;
use DateTimeImmutable;
use Packman\Attributes\Api;

/** Issues and revokes direct permissions in the current tenant database. */
#[Api(since: '1.0')]
interface DirectGrants
{
    public function grant(Principal $principal, string $entityKey, string $action, ?DateTimeImmutable $expiresAt = null): void;

    public function revoke(Principal $principal, string $entityKey, string $action): void;
}
