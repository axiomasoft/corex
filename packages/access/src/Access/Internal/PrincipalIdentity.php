<?php

declare(strict_types=1);

namespace CoreX\Access\Access\Internal;

use AzGuard\Concerns\HasPermissions as HasPermissionsConcern;
use AzGuard\Contracts\HasPermissions as HasPermissionsContract;
use CoreX\Access\Contracts\Principal;
use CoreX\Access\Enums\PrincipalType;
use CoreX\Access\Identity\Internal\PrincipalUserProvider;
use Illuminate\Contracts\Auth\Authenticatable;
use Override;

/**
 * Principal carrier for the AzGuard resolution chain (D101) — a user, agent
 * (B-13) or api_key, none of which need a `users` row (AC-25): the morph
 * class is the principal type itself, the auth identifier its uuid. Wires
 * AzGuard's own HasPermissions concern so DefaultAccessChecker::allowed()
 * asks the REAL public AzGuard path (premortem C1's round-trip), not a
 * private lookup.
 */
final class PrincipalIdentity implements Authenticatable, HasPermissionsContract, Principal
{
    use HasPermissionsConcern;

    public function __construct(
        private readonly PrincipalType $type,
        private readonly string $id,
        private readonly ?string $passwordHash = null,
    ) {}

    public static function fromPrincipal(Principal $principal): self
    {
        return new self($principal->principalType(), $principal->principalId());
    }

    #[Override]
    public function principalType(): PrincipalType
    {
        return $this->type;
    }

    #[Override]
    public function principalId(): string
    {
        return $this->id;
    }

    /** AzGuard's grant sources (e.g. DatabaseRoleGrantSource) key on this — a plain string, not a class-string, for these non-Eloquent principals. */
    public function getMorphClass(): string
    {
        return $this->type->value;
    }

    #[Override]
    public function getAuthIdentifierName(): string
    {
        return 'id';
    }

    #[Override]
    public function getAuthIdentifier(): string
    {
        return $this->id;
    }

    #[Override]
    public function getAuthPasswordName(): string
    {
        return 'password';
    }

    /**
     * `null` for the `agent`/`api_key` lanes (no password) and for the OIDC
     * lane (never constructed with a hash — `Auth::loginUsingId()` skips
     * credential validation entirely); the local login lane
     * ({@see PrincipalUserProvider}) is the only constructor of a
     * `PrincipalIdentity` that passes one.
     */
    #[Override]
    public function getAuthPassword(): ?string
    {
        return $this->passwordHash;
    }

    #[Override]
    public function getRememberToken(): ?string
    {
        return null;
    }

    #[Override]
    public function setRememberToken(mixed $value): void
    {
        // No remember-me session for a non-interactive principal.
    }

    #[Override]
    public function getRememberTokenName(): string
    {
        return '';
    }
}
