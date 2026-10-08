<?php

declare(strict_types=1);

namespace CoreX\Access\Access\Laravel;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use CoreX\Context\Contracts\Principal;
use CoreX\Context\Enums\PrincipalType;
use Illuminate\Contracts\Auth\Authenticatable;
use Override;

/**
 * Authentication carrier for user, agent and api_key principals. The morph
 * class is the principal type and the auth identifier is its UUID.
 */
#[Spi(kind: SpiKind::Call, since: '1.0')]
final readonly class PrincipalIdentity implements Authenticatable, Principal
{
    public function __construct(
        private PrincipalType $type,
        private string $id,
        private ?string $passwordHash = null,
    ) {}

    public static function fromPrincipal(Principal $principal): self
    {
        return new self(type: $principal->principalType(), id: $principal->principalId());
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

    /** A plain principal type, never an Eloquent class name. */
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
     * An empty string for the `agent`/`api_key` lanes (no password) and for the OIDC
     * lane (never constructed with a hash — `Auth::loginUsingId()` skips
     * credential validation entirely); the local login lane
     * ({@see CoreX\Access\Identity\Laravel\PrincipalUserProvider}) is the only constructor of a
     * `PrincipalIdentity` that passes one.
     */
    #[Override]
    public function getAuthPassword(): string
    {
        return $this->passwordHash ?? '';
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
