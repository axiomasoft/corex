<?php

declare(strict_types=1);

namespace CoreX\Tenancy\Tests\Stubs;

use Illuminate\Contracts\Auth\Authenticatable;
use Override;

/**
 * Minimal {@see Authenticatable} standing in for `corex/access`'s
 * `PrincipalIdentity` (D125, OQ-18 variant A) — corex/tenancy does not
 * depend on corex/access (AC-28), so the OIDC lane's own test harness cannot
 * import the real class either; only the auth identifier matters for
 * these tests (parity/half 2 is proven by {@see StubIdentityProvider}
 * recording which id it was asked to retrieve).
 */
final class StubIdentity implements Authenticatable
{
    public function __construct(
        private readonly string $id,
    ) {}

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

    #[Override]
    public function getAuthPassword(): ?string
    {
        return null;
    }

    #[Override]
    public function getRememberToken(): ?string
    {
        return null;
    }

    #[Override]
    public function setRememberToken(mixed $value): void
    {
        // No remember-me for this stub.
    }

    #[Override]
    public function getRememberTokenName(): string
    {
        return '';
    }
}
