<?php

declare(strict_types=1);

namespace CoreX\AccessAzguard\Tests\Stubs;

use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Minimal user double exposing the AzGuard permission API that
 * TenantContextGuard consults, with configurable answers.
 */
final class ContextUser implements Authenticatable
{
    public function __construct(
        public bool $scoped = false,
        public bool $global = false,
    ) {}

    public function hasScopedPermission(string $permission, object $entity): bool
    {
        return $this->scoped;
    }

    public function hasPermission(string $permission, string $panelId = 'app'): bool
    {
        return $this->global;
    }

    public function getAuthIdentifierName(): string
    {
        return 'id';
    }

    public function getAuthIdentifier(): string
    {
        return 'context-user';
    }

    public function getAuthPasswordName(): string
    {
        return 'password';
    }

    public function getAuthPassword(): string
    {
        return '';
    }

    public function getRememberToken(): string
    {
        return '';
    }

    public function setRememberToken($value): void
    {
        // no-op
    }

    public function getRememberTokenName(): string
    {
        return '';
    }
}
