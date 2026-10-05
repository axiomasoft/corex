<?php

declare(strict_types=1);

namespace CoreX\AccessAzguard\Internal;

use Illuminate\Database\ConnectionResolverInterface;

/** Tenant-local link between CoreX role IDs and AzGuard's numeric role IDs. */
final readonly class RoleLinkStore
{
    public function __construct(private ConnectionResolverInterface $connections) {}

    public function upsert(string $roleId, int $azguardRoleId): void
    {
        $this->connections->connection()->table('aut_azguard_role_links')->updateOrInsert(
            ['role_id' => $roleId],
            ['azguard_role_id' => $azguardRoleId],
        );
    }
}
