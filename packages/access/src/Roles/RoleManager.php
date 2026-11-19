<?php

declare(strict_types=1);

namespace CoreX\Access\Roles;

use CoreX\Access\Data\RecordContext;
use CoreX\Access\Enums\Scope;
use CoreX\Context\Contracts\Principal;
use CoreX\Context\Enums\PrincipalType;
use DateTimeImmutable;
use Packman\Attributes\Api;

/** Manages roles and assignments in the current tenant database. */
#[Api(since: '1.0')]
interface RoleManager
{
    /** @param list<PrincipalType> $assignableTo */
    public function create(string $slug, string $name, array $assignableTo = [PrincipalType::User]): string;

    public function setScope(string $roleId, string $entityKey, string $action, Scope $scope, ?RecordContext $context = null): void;

    public function assign(string $roleId, Principal $principal, ?string $workspaceId = null, ?DateTimeImmutable $expiresAt = null): void;

    public function unassign(string $roleId, Principal $principal, ?string $workspaceId = null): void;
}
